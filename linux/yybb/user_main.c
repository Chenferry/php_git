
#include "typedef_board.h"


pthread_t udp_listen_thread;
pthread_t tcp_thread;
pthread_mutex_t mutex;

uint8 gAddFlag=0;
uint32 gHicID;
uint32 gUserId;
uint8 g_mac_str[16];
uint8 g_sta_mac[8];
int main(int argc, char** argv)
{ 
//int pthread_create(pthread_t *thread, const pthread_attr_t *attr, void *(*start_routine)(void*),void *arg);
//thread            指向线程标识符的指针，使用这个标识符来引用新线程
//attr              设置线程属性，设为NULL则生成默认属性的线程
//start_routine     线程运行函数的起始位置
//arg               线程运行函数的参数
    int ret;
    uint8 i;
    char mactmp[16];
    get_mac(g_sta_mac);
    PRINTF("mac:");
    for(i=0; i< 8; i++)
    {
    PRINTF("%x", g_sta_mac[i]);
    }
    PRINTF("\r\n");
    pthread_mutex_init(&mutex,NULL);
    ret = pthread_create(&udp_listen_thread, NULL, (void*)user_udp_listen, NULL);
    if(ret != 0)
    {
        PRINTF("udp_listen_thread create error\r\n");
    }
    ret = pthread_create(&tcp_thread, NULL, (void*)tcp_check_connect, NULL);
    if(ret != 0)
    {
        PRINTF("tcp_thread create error\r\n");
    }
    user_flash_init();
    pthread_join(udp_listen_thread,NULL); 
    pthread_join(tcp_thread,NULL); 
    return 0;
}
