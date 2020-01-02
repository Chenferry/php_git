#include "header.h"

sint16 g_main_sock_fd = -1;
uint16 user_msg_send(uint8 *data, uint16 len)
{
    uint16 ret = -1;
    if (NULL == data)
    {
        return -1;
    }

    if (0 > g_main_sock_fd)
        return ret;
    ret = write(g_main_sock_fd, data, len);

    return ret;
}

uint8 user_socket_create(uint8 *host, uint16 port, uint8 isSSL)
{
    sint16 ret;
    int sockfd;
    struct sockaddr_in sock_addr;
    struct hostent *hptr;
    char ipAddr[16];

    if (NULL == host)
    {
        return -1;
    }

    memset(&sock_addr, 0, sizeof(sock_addr));
    sock_addr.sin_family = AF_INET;
    sock_addr.sin_port = htons(port);
    // sock_addr.sin_addr.s_addr = inet_addr(DEMO_SERVER);
    // sock_addr.sin_port = htons(DEMO_SERVER_PORT);
    hptr = gethostbyname(host);
    if (NULL == hptr)
    {
        return -1;
    }
    else
    {
        sprintf(ipAddr,"%s",inet_ntoa(*((struct in_addr *)hptr->h_addr)));
        sock_addr.sin_addr.s_addr=inet_addr(ipAddr);
    }

    sockfd = socket(PF_INET, SOCK_STREAM, 0);
    if (sockfd < 0) {
        os_printf("create main socket failed\n");
        return -1;
    }

    sint16 flag = 1;
    setsockopt(sockfd, SOL_SOCKET, SO_REUSEADDR, &flag, sizeof(sint16));

    os_printf("main socket:%d\n",sockfd);

    ret = connect(sockfd, (struct sockaddr*)&sock_addr, sizeof(sock_addr));
    if (ret) {
        os_printf("connect failed\n");
        close(sockfd);
        return -1;
    }

    g_main_sock_fd = sockfd;
    os_printf("g_main_sock_fd:%d socket:%d\n",g_main_sock_fd,sockfd);

    return 0;
}

uint8 user_create_app_conn(uint8 *host, uint16 port, uint8 isSSL)
{
    sint16 ret;

    if (NULL == host)
    {
        return -1;
    }

    printf("user_create_app_conn host:%s port:%d isSSL:%d\n", host,port,isSSL);

    ret = user_socket_create(host, port, isSSL);
    if (0 != ret)
    {
        printf("user_create_app_conn fail:%d\n", ret);
        return ret;
    }

    // 连接成功后,去除请求获取服务器地址线程,然后上报mac信息,最后分别创建状态机主线程和接收数据任务线程
    // report mac addr to server when connect ok
    // mac-devType-token-hicid-attrIndex
    // mac-2-token-hicid-0
    uint8 msg[64] = {0};
    uint8 secury[8];
    uint8 keyStr[32] = "FFFF";

    sprintf(msg, "%s-%d-%s-%d-%d\n", g_mac_str, 2, keyStr, gHicID, 0);
    os_printf("socket on conn msg:%s\n",msg);
    ret = user_msg_send(msg, strlen(msg));

    //user_dev_app_start_task();

    os_printf("out user_create_app_conn ret:%d\n",ret);
    return ret;
}
