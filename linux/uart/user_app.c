#include "header.h"
#define ITEM_SERVER_URL  "http://item.jia.mn/App/a/frame/dispatch.php"
extern int fd;

pthread_mutex_t g_mutex;
uint8 g_mac_str[16];
uint8 g_sta_mac[8];
#define MAX_PARAMS_BUF_LEN    (256)

int get_mac_addr(char *ifac, char* mac)
{
    struct ifreq tmp;
    int sock_mac;
    //char mac_addr[30];
    sock_mac = socket(AF_INET, SOCK_STREAM, 0);
    if( sock_mac == -1){
        return -1;
    }
    memset(&tmp,0,sizeof(tmp));
    // strncpy(tmp.ifr_name, ifac, sizeof(tmp.ifr_name)-1 );
    strcpy(tmp.ifr_name, ifac);
    if( (ioctl( sock_mac, SIOCGIFHWADDR, &tmp)) < 0 ){
        return -1;
    }

    close(sock_mac);
    memcpy(mac,tmp.ifr_hwaddr.sa_data,6);

    return 0;
}

void user_get_item_addr()
{
    uint8 security[8] = {0};
    int8 isSSL = 0;

    uint8 params[MAX_PARAMS_BUF_LEN] = {0};
    // memset(params, 0, MAX_PARAMS_BUF_LEN);
    // printf("gHicID %d gUserId %d\n",gHicID,gUserId);
    sprintf(params, "mac=%s&hicid=%d&userid=%d&isssl=%d",
        g_mac_str, gHicID, gUserId, isSSL);
    printf("params:%s\n", params);
    http_post(ITEM_SERVER_URL, params, "Content-Type: application/x-www-form-urlencoded\r\n", http_response_callback);
}

void *user_uart_recv(void *arg)
{
        int len;  
        int cnt=0;
        char rcv_buf[2560]; 
        while (1) //循环读取数据    
        {   
            len = UART0_Recv(fd, rcv_buf,sizeof(rcv_buf));    
            if(len > 0)    
            {    
                rcv_buf[len] = '\0';    
                printf("receive data is %s\n",rcv_buf);    
            }    
            else    
            {    
                if(cnt++>10)
                    break;
                printf("cannot receive data\n");    
            }    
            sleep(1);    
        }                
        UART0_Close(fd);       
}
uint8 user_http_callback_handle(uint8 *pMsg)
{
    uint8 ret;
    uint8 method[32] = {0};
    cJSON *json = NULL;
    cJSON *item = NULL;

    pthread_mutex_lock(&g_mutex);
    if (NULL == pMsg)
    {
        pthread_mutex_unlock(&g_mutex);
        return false;
    }

    json = cJSON_Parse(pMsg);
    if (NULL == json)
    {
        pthread_mutex_unlock(&g_mutex);
        return false;
    }

    cJSON *ptr = cJSON_GetObjectItem(json, "METHOD");
    if (NULL == ptr)
    {
        pthread_mutex_unlock(&g_mutex);
        return false;
    }

    memcpy(method, ptr->valuestring, strlen(ptr->valuestring));
    os_printf("method: %s\r\n", method);
    if (0 == strncmp(method, "item_addr", 9))
    {
        uint8 url[64] = {0};
        uint16 port = 80;
        uint32 hicid = 0;
        uint8 isSSL = 0;
        uint8 isLocal = 0;

        item = cJSON_GetObjectItem(json, "SERVER");
        if (NULL != item)
        {
            memcpy(url, item->valuestring, strlen(item->valuestring));
        }
        item = cJSON_GetObjectItem(json, "DEVRPORT");
        if (NULL != item)
        {
            port = item->valueint ? item->valueint : 80;
        }
        item = cJSON_GetObjectItem(json, "ISSSL");
        if (NULL != item)
        {
            isSSL = item->valueint ? item->valueint : 0;
        }
        item = cJSON_GetObjectItem(json, "LOCAL");
        if (NULL != item)
        {// 0琛ㄧず鏈嶅姟鍣紝1琛ㄧず涓绘満
            isLocal = item->valueint ? item->valueint : 0;
        }
        item = cJSON_GetObjectItem(json, "HICID");
        os_printf("url[%s] port[%d] gHicID[%x] hicid[%x] isLocal[%d]\n",url, port, gHicID, hicid, isLocal);      
         ret = user_create_app_conn(url, port, isSSL);
    }
    pthread_mutex_unlock(&g_mutex);
    cJSON_Delete(json);
    cJSON_Delete(ptr);
    cJSON_Delete(item);
}
