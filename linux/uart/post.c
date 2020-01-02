/*
 * =====================================================================================
 *
 *       Filename:  user_http.c
 *
 *    Description:  
 *
 *        Version:  1.0
 *        Created:  01/11/2018 03:14:59 PM
 *       Revision:  none
 *       Compiler:  gcc
 *
 *         Author:  YOUR NAME (), 
 *   Organization:  
 *
 * =====================================================================================
 */

#include "header.h"

#define HTTP_POST "POST %s HTTP/1.0\r\nHost: %s:%d\r\n%sContent-Length: %d\r\n\r\n%s"
#define HTTP_GET "GET %s HTTP/1.0\r\nHost: %s:%d\r\nAccept: */*\r\n\r\n"


uint8 user_http_callback_handle(uint8 *pMsg);
static void dns_callback(const char * hostname, ip_addr_t * addr, void * arg);

// Debug output.
// #if 0
// #define PRINTF(...) printf(__VA_ARGS__)
// #else
// #define PRINTF(...)
// #endif

// Internal state.
typedef struct {
    char * path;
    int port;
    char * post_data;
    char * headers;
    char * hostname;
    char * buffer;
    int buffer_size;
    bool secure;
    http_callback user_callback;
} request_args;


static char * esp_strdup(const char * str)
{
    if (str == NULL) {
        return NULL;
    }
    char * new_str = (char *)malloc(strlen(str) + 1); // 1 for null character
    if (new_str == NULL) {
        PRINTF("esp_strdup: malloc error");
        return NULL;
    }
    strcpy(new_str, str);
    return new_str;
}

static int 
esp_isupper(char c)
{
    return (c >= 'A' && c <= 'Z');
}

static int 
esp_isalpha(char c)
{
    return ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z'));
}


static int 
esp_isspace(char c)
{
    return (c == ' ' || c == '\t' || c == '\n' || c == '\12');
}

static int 
esp_isdigit(char c)
{
    return (c >= '0' && c <= '9');
}

static int chunked_decode(char * chunked, int size)
{
    char *src = chunked;
    char *end = chunked + size;
    int i, dst = 0;

    do
    {
        //[chunk-size]
        i = strtol(src, (char **) NULL, 16);
        PRINTF("Chunk Size:%d\r\n", i);
        if (i <= 0) 
            break;
        //[chunk-size-end-ptr]
        src = (char *)strstr(src, "\r\n") + 2;
        //[chunk-data]
        memmove(&chunked[dst], src, i);
        src += i + 2; /* CRLF */
        dst += i;
    } while (src < end);

    //
    //footer CRLF
    //

    /* decoded size */
    return dst;
}

/*
 * Parse an URL of the form http://host:port/path
 * <host> can be a hostname or an IP address
 * <port> is optional
 */
void http_post(const char * url, const char * post_data, const char * headers, http_callback user_callback)
{
    // FIXME: handle HTTP auth with http://user:pass@host/
    // FIXME: get rid of the #anchor part if present.

    char hostname[128] = "";
    int port = 80;
    bool secure = false;

    bool is_http  = strncmp(url, "http://",  strlen("http://"))  == 0;
    bool is_https = strncmp(url, "https://", strlen("https://")) == 0;

    if (is_http)
        url += strlen("http://"); // Get rid of the protocol.
    else if (is_https) {
        port = 443;
        secure = true;
        url += strlen("https://"); // Get rid of the protocol.
    } else {
        PRINTF("URL is not HTTP or HTTPS %s\n", url);
        return;
    }

    char * path = strchr(url, '/');
    if (path == NULL) {
        path = strchr(url, '\0'); // Pointer to end of string.
    }

    char * colon = strchr(url, ':');
    if (colon > path) {
        colon = NULL; // Limit the search to characters before the path.
    }

    if (colon == NULL) { // The port is not present.
        memcpy(hostname, url, path - url);
        hostname[path - url] = '\0';
    }
    else {
        port = atoi(colon + 1);
        if (port == 0) {
            PRINTF("Port error %s\n", url);
            return;
        }

        memcpy(hostname, url, colon - url);
        hostname[colon - url] = '\0';
    }


    if (path[0] == '\0') { // Empty path is not allowed.
        path = "/";
    }

    PRINTF("hostname=%s\n", hostname);
    PRINTF("port=%d\n", port);
    PRINTF("path=%s\n", path);
    user_http_req(hostname, port, secure, path, post_data, headers);
}

void http_get(const char * url, const char * headers, http_callback user_callback)
{
    http_post(url, NULL, headers, user_callback);
}

void http_response_callback(char * response_body, int http_status, char * response_headers, int body_size)
{
    PRINTF("http_status=%d\n", http_status);
    if (http_status != HTTP_STATUS_GENERIC_ERROR) {
        PRINTF("strlen(headers)=%d\n", strlen(response_headers));
        PRINTF("body_size=%d\n", body_size);
        PRINTF("body=%s\n", response_body); // FIXME: this does not handle binary data.
        user_http_callback_handle(response_body);
    }
}

void user_http_req(const char * hostname, int port, bool secure,
    const char * path, const char * post_data, const char * headers)
{
    struct hostent *server;
    struct sockaddr_in serv_addr;
    int sockfd, bytes, sent, received, total,iResult;
    char buf[256];
    char *response;
    char *response_body;
    int len = 0;

    const char * method = "GET";
    char post_headers[32] = "";

    if (NULL != post_data)
        len = sprintf(buf, HTTP_POST, path, hostname, port, headers, strlen(post_data), post_data);
    else
        len = sprintf(buf, HTTP_GET, path, hostname, port);

    PRINTF("Request:\n%d:%s\n",len,buf);

    /* create the socket */
    sockfd = socket(AF_INET, SOCK_STREAM, 0);
    if (sockfd < 0)
    {
        PRINTF("create socket fail!\n");
        return;
    }

    // set socket force close
    // lwip_force_close_set(1);
    // sint16 flag = 1000;
    // setsockopt(sockfd, SOL_SOCKET, SO_RCVTIMEO, &flag, sizeof(sint16));
    sint16 flag = 1;
    setsockopt(sockfd, SOL_SOCKET, SO_REUSEADDR, &flag, sizeof(sint16));

    struct timeval timeout={3,0};//3s
    setsockopt(sockfd,SOL_SOCKET,SO_SNDTIMEO,(const char*)&timeout,sizeof(timeout));
    setsockopt(sockfd,SOL_SOCKET,SO_RCVTIMEO,(const char*)&timeout,sizeof(timeout));
    // lwip_set_non_block(sockfd);

    /* lookup the ip address */
    server = gethostbyname(hostname);
    if (server == NULL)
    {
        PRINTF("DNS failed for %s\n", hostname);
        close(sockfd);
        return;
    }

    /* fill in the structure */
    memset(&serv_addr,0,sizeof(serv_addr));
    serv_addr.sin_family = AF_INET;
    serv_addr.sin_port = htons(port);
    memcpy(&serv_addr.sin_addr.s_addr,server->h_addr,server->h_length);

    /* connect the socket */
    if (connect(sockfd,(struct sockaddr *)&serv_addr,sizeof(serv_addr)) < 0)
    {
        PRINTF("connecting failed\n");
        close(sockfd);
        return;
    }

    PRINTF("send the request\n");
#if 1
    /* send the request */
    // total = len;
    // sent = 0;
    // do {
    //     bytes = write(sockfd,buf+sent,total-sent);
    //     if (bytes < 0)
    //     {
    //         PRINTF("ERROR writing buf to socket\n");
    //         close(sockfd);
    //         run = 0;
    //         return;
    //     }
    //     if (bytes == 0)
    //         break;
    //     sent+=bytes;
    // } while (sent < total);

    bytes = write(sockfd,buf,len);
    if (bytes < 0)
    {
        PRINTF("ERROR writing buf to socket\n");
        close(sockfd);
        return;
    }

    /* receive the response */
    if (NULL == (response = (char *)os_malloc(512))) {
        PRINTF("os_malloc fail for 256 size\n");
        close(sockfd);
        return;
    }
    PRINTF("receive the response\n");
    bytes = recv(sockfd, response, 512, 0);
    if (bytes < 0)
    {
        PRINTF("recv response fail\n");
        free(response);
        close(sockfd);
        return;
    }
    // total = sizeof(response)-1;
    // received = 0;
    // do {
    //    bytes = recv(sockfd, response, 256, 0);
    //     if (bytes < 0)
    //     {
    //         PRINTF("recv response fail\n");
    //         free(response);
    //         close(sockfd);
    //         run = 0;
    //         return;
    //     }
    //    if (bytes == 0)
    //        break;
    //    received+=bytes;
    // } while (1);
#else
    struct timeval timeout;
    timeout.tv_sec = 3;
    timeout.tv_usec = 0;
    fd_set read_set, write_set, error_set;

    FD_ZERO( &read_set);
    FD_SET(sockfd,  &read_set);
    write_set = read_set;
    error_set = read_set;
    iResult = select(sockfd + 1, &read_set, &write_set, &error_set, &timeout);
    if(iResult == -1){
        PRINTF("select failed\n");
        close(sockfd);
        return;
    }
    if(iResult == 0){
        PRINTF("select timeout\n");
        close(sockfd);
        return;
    }
    if(FD_ISSET(sockfd , &error_set)){    // error happen
        PRINTF("error happen\n");
        close(sockfd);
        return;
    }
    PRINTF("writeable\n");
    if (FD_ISSET(sockfd, &write_set))
    {
        received = 0;
        do {
           bytes = recv(sockfd, response, 512, 0);
            if (bytes < 0)
            {
                PRINTF("recv response fail\n");
                free(response);
                close(sockfd);
                return;
            }
           if (bytes == 0)
               break;
           received+=bytes;
        } while (1);

    }
    PRINTF("readable\n");
    if(FD_ISSET(sockfd , &read_set)){    // readable
        do{
            // iResult  =  recv(sockfd, response, 256,  MSG_DONTWAIT);
            iResult  =  recv(sockfd, response, 512,  0);
        }while(iResult > 0);
    }
#endif
    /* close the socket */
    close(sockfd);

    /* process response */
    PRINTF("Response:\n%s\n",response);
    response_body = http_parse_result(response);
    user_http_callback_handle(response_body);
    free(response);
    free(response_body);

    return;
}

char* http_parse_result(const char*resp)
{
    char *ptr = NULL; 
    char *response = NULL;
    ptr = (char*)strstr(resp,"HTTP/1.1");
    if(!ptr){
        ptr = (char*)strstr(resp,"HTTP/1.0");
        if (!ptr)
        {
            PRINTF("unknown http version\n");
            return NULL;
        }
    }
    if(atoi(ptr + 9)!=200){
        PRINTF("result:\n%s\n",resp);
        return NULL;
    }

    ptr = (char*)strstr(resp,"\r\n\r\n");
    if(!ptr){
        PRINTF("ptr is NULL\n");
        return NULL;
    }
    response = (char *)malloc(strlen(ptr)+1);
    if(!response){
        PRINTF("malloc failed \n");
        return NULL;
    }

    if (NULL != (ptr + 4))
        strcpy(response,ptr+4);
    else
        return NULL;
    // PRINTF("Response content:\n%s\n",response);
    return response;
}
