#include "typedef_board.h"

#define ITEM_SERVER_URL  "http://item.jia.mn/App/a/frame/dispatch.php"
#define ITEM_SERVER_TEST  "item.jia.mn"

#define HTTP_POST "POST %s HTTP/1.0\r\nHost: %s:%d\r\n%sContent-Length: %d\r\n\r\n%s"
#define HTTP_PORT 80

#define POST_HEADER "Content-Type: application/x-www-form-urlencoded\r\n"
int connect_flag=-1;

int  g_main_sock = -1;
extern uint32 gHicID;
extern uint32 gUserId;
extern uint8 g_mac_str[16];
extern uint8 g_sta_mac[8];

void msg_deal(char *data);

void tcp_check_connect(void)
{
    while(1)
    {
        if(gHicID == 0)
        {
            sleep(5);
        }
        else
        {
            break;
        }
    }
    if(connect_flag==-1)
    {
        int sockfd;
        struct hostent  *htpr;
        struct sockaddr_in server_addr;
        struct sockaddr_in client_addr;
        char ipAddr[16];
        char postbuff[512];
        uint8 params[256] = {0};
        uint8 len;
        uint8 bytes;
        char *response;
        char *response_body;        
    // memset(params, 0, MAX_PARAMS_BUF_LEN);
    // printf("gHicID %d gUserId %d\n",gHicID,gUserId);
        snprintf(g_mac_str, 16, "%02x%02x%02x%02x%02x%02x%02x%02x",
        g_sta_mac[0],g_sta_mac[1],g_sta_mac[2],g_sta_mac[3],
        g_sta_mac[4],g_sta_mac[5],g_sta_mac[6],g_sta_mac[7]);  
        sprintf(params, "mac=%s&hicid=%d&userid=%d&isssl=%d",
        g_mac_str, gHicID, gUserId, 0);                

        sockfd = socket(AF_INET,SOCK_STREAM,0);
        if(sockfd == -1)
        {
            PRINTF("fail to socket %s\r\n",__FUNCTION__);
            return;
        }
        bzero(&server_addr, sizeof(server_addr));
        server_addr.sin_family = AF_INET;
        server_addr.sin_port=htons(80);
        htpr = gethostbyname(ITEM_SERVER_TEST);
        if(htpr == NULL)
        {
            PRINTF("not find \r\n");
            return;
        }
        sprintf(ipAddr, "%s", inet_ntoa((*(struct in_addr *)htpr->h_addr_list[0])));
        server_addr.sin_addr.s_addr =(inet_addr(ipAddr));
        PRINTF("h_name:%s\r\n", htpr->h_name);     
        PRINTF("h_length:%d\r\n", htpr->h_length); 
        PRINTF("h_addr:%s\r\n", inet_ntoa((*(struct in_addr *)htpr->h_addr_list[0])));
        len = sprintf(postbuff, HTTP_POST, ITEM_SERVER_URL, htpr->h_name, HTTP_PORT, POST_HEADER,strlen(params), params);
        PRINTF("%s\r\n",postbuff);        
        
        int16 flag = 1;
        setsockopt(sockfd, SOL_SOCKET, SO_REUSEADDR, &flag, sizeof(int16));

        struct timeval timeout={3,0};//设置超时
        setsockopt(sockfd,SOL_SOCKET,SO_SNDTIMEO,(const char*)&timeout,sizeof(timeout));
        setsockopt(sockfd,SOL_SOCKET,SO_RCVTIMEO,(const char*)&timeout,sizeof(timeout));

        if(connect(sockfd, (struct sockaddr *)&server_addr, sizeof(struct sockaddr))<0)
        {
            PRINTF("connect is failedr\r\n");
            close(sockfd);
        }
        PRINTF("connect is ok send post\r\n");

        bytes = write(sockfd, postbuff, len);
        if(bytes < 0)
        {
            PRINTF("write error: %s", strerror(errno));
            close(sockfd);
            return;
        }
        response = (char*)malloc(512);
        if(response == NULL)
        {
            PRINTF("os_malloc fail for 512 size\n");
            close(sockfd);  
            return;
        }
        PRINTF("receive the response\n");
        bytes = read(sockfd, response, 512);
        if(bytes < 0)
        {
            PRINTF("socket read error\r\n");
            close(sockfd);
        }
        PRINTF("respond %s\r\n", response);
        uint8  *ptr = strstr(response, "\r\n\r\n");
        PRINTF("respond %s\r\n", ptr+4);    
        char ipaddr[16];
        PRINTF("ipaddr len[%d]\r\n", sizeof(ipaddr));       
        PRINTF("ipaddr len[%d]\r\n", strlen(ipaddr));        
        int port;
        get_socket_connect_info(ptr+4,ipaddr, &port);
        create_socket_connect(ipaddr,port);
        close(sockfd);
        free(response);
    }
}

void get_socket_connect_info(char *data, char* ipaddr, int* port)
{
    
    cJSON *json;
    json = cJSON_Parse(data);
    cJSON* itemtmp= cJSON_GetObjectItem(json, "SERVER");
    strcpy(ipaddr, itemtmp->valuestring);
    itemtmp = cJSON_GetObjectItem(json, "DEVRPORT");
    *port = itemtmp->valueint;
    cJSON_Delete(json);
}
void create_socket_connect(char ipaddr[], int port)
{
    PRINTF("ipaddr len[%d]\r\n", sizeof(ipaddr));
    PRINTF("ipaddr len[%d]\r\n", strlen(ipaddr));
    struct hostent  *htpr;
    struct sockaddr_in server_addr;
    char ipAddr[16];
    uint8 len;
    uint8 bytes;
    int sockfd;
    sockfd = socket(AF_INET,SOCK_STREAM,0);
    if(sockfd == -1)
    {
        PRINTF("fail to socket %s\r\n",__FUNCTION__);
        return;
    }
    bzero(&server_addr, sizeof(server_addr));
    server_addr.sin_family = AF_INET;
    server_addr.sin_port=htons(port);
    htpr = gethostbyname(ipaddr);
    if(htpr == NULL)
    {
        PRINTF("not find \r\n");
        return;
    }
    server_addr.sin_addr.s_addr =(inet_addr(ipaddr));
    PRINTF("h_name:%s\r\n", htpr->h_name);     
    PRINTF("h_length:%d\r\n", htpr->h_length); 
    PRINTF("h_addr:%s\r\n", inet_ntoa((*(struct in_addr *)htpr->h_addr_list[0])));
    
    int16 flag = 1;
    setsockopt(sockfd, SOL_SOCKET, SO_REUSEADDR, &flag, sizeof(int16));

    if(connect(sockfd, (struct sockaddr *)&server_addr, sizeof(struct sockaddr))<0)
    {
        PRINTF("connect is failedr\r\n");
        close(sockfd);
    }
    PRINTF("connect is ok\r\n");
   g_main_sock = sockfd;
}

void tcp_msg_rec(void)
{
    char rcvbuf[1024];
    int bytes;
    if(g_main_sock >0)
    bytes = read(sockfd, response, 512);  
    if(bytes >0)
    {

    }
}

int get_mac(uint8 *mac)
{
    struct ifreq ifreq;    //ifreq结构体常用来配置和获取ip地址
    int sock;
 
    if ((sock = socket (AF_INET, SOCK_STREAM, 0)) < 0)
    {
        perror ("mac");
        return -1;
    }
    strcpy (ifreq.ifr_name, "eth0");    //Currently, only get eth0
 
    if (ioctl (sock, SIOCGIFHWADDR, &ifreq) < 0)
    {
        PRINTF ("ioctl");
        return -1;
    }
    close(sock);
    memcpy(mac, ifreq.ifr_hwaddr.sa_data, 6);
}
