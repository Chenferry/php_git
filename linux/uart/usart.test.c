/*********************************************************************************
 *      Copyright:  (C) 2018 Yujie
 *                  All rights reserved.
 *
 *       Filename:  usart_test.c
 *    Description:  ���ڲ���
 *                 
 *        Version:  1.0.0(08/27/2018)
 *         Author:  yanhuan <yanhuanmini@foxmail.com>
 *      ChangeLog:  1, Release initial version on "08/23/2018 17:28:51 PM"
 *                 
 ********************************************************************************/
 
#include "usart.h"
#include "header.h"     /*��׼�����������*/    

pthread_t g_bcast_recv_task;
pthread_t g_uart_recv_task;

int fd = -1;           //�ļ����������ȶ���һ��������޹ص�ֵ����ֹfdΪ����ֵ���³����bug    
int main(int argc, char **argv)    
{
    int err;               //���ص��ú�����״̬    
    int ret;
    int i;
    char send_buf[256];
    if(argc != 2)    
    {    
        printf("Usage: %s /dev/ttyUSB0 \n",argv[0]);
        printf("open failure : %s\n", strerror(errno));
    
        return FALSE;    
    }    
     fd = UART0_Open(fd,argv[1]); //�򿪴��ڣ������ļ�������   
     // fd=open("dev/ttyS1", O_RDWR);
     printf("fd=%d\n",fd);
     do  
    {    
        err = UART0_Init(fd,115200,0,8,1,'N');    
        printf("Set Port Exactly!\n"); 
        sleep(1);   
    }while(FALSE == err || FALSE == fd);    
    printf("Set Port is ok!\n");   
     get_mac_addr("eth0", g_sta_mac);
    for(i=0;i<8;i++)
    {
        printf("%02x ", g_sta_mac[i]);
    }
    printf("\n");
    snprintf(g_mac_str, 16, "%02x%02x%02x%02x%02x%02x%02x%02x",
        g_sta_mac[0],g_sta_mac[1],g_sta_mac[2],g_sta_mac[3],
        g_sta_mac[4],g_sta_mac[5],g_sta_mac[6],g_sta_mac[7]);   
        
    ret = pthread_create(&g_uart_recv_task, NULL, user_uart_recv, NULL);  
    if (0 != ret)
    {
        os_printf("create user_uart_recv thread fail ret[%d]\n", ret);
        return -1;
    }  

    ret = pthread_create(&g_bcast_recv_task, NULL, user_listen_brst, NULL);
    if (0 != ret)
    {
        os_printf("create conn status ckeck thread fail ret[%d]\n", ret);
        return -1;
    }

    pthread_join(g_uart_recv_task, NULL);
    pthread_join(g_bcast_recv_task, NULL); 
    return 0;
}    
