#include "typedef_board.h"

#define BROADCAST_LISTEN_PORT (9301)
#define UDP_MAX_LEN 1024
#define DECRYPTION_CONSTANT (0xAABBCCDD)
extern uint32 gHicID;
extern uint32 gUserId;

void user_udp_listen(void)
{
    char buf[UDP_MAX_LEN];
    int recvBytes = 0;
    int sockfd = socket(AF_INET, SOCK_DGRAM, 0);  
    struct sockaddr_in clientAddr;
    socklen_t addrLen;
    if (sockfd == -1)    
        PRINTF("socket error");  
    struct sockaddr_in servAddr;    
    servAddr.sin_family = AF_INET;    
    servAddr.sin_addr.s_addr = htonl(INADDR_ANY);    
    servAddr.sin_port = htons(BROADCAST_LISTEN_PORT);    
    if (bind(sockfd, (const struct sockaddr *)&servAddr, sizeof(servAddr)) == -1)    
        PRINTF("bind error");            
    memset(buf, 0, UDP_MAX_LEN);
    addrLen = sizeof(clientAddr);    
    memset(&clientAddr, 0, addrLen); 
    PRINTF("udp start recv\r\n");
    while(1)
    {
        recvBytes = recvfrom(sockfd, buf, UDP_MAX_LEN, 0, (struct sockaddr *)&clientAddr, &addrLen);
        if(recvBytes < 0)
        {
            if(errno == EINTR)//need to reconnect
            {
                PRINTF("connect is close\r\n");
            }
            else
            {
                PRINTF("recv is error\r\n");
            }
        }
        PRINTF("client ip:%s\r\n", inet_ntoa(clientAddr.sin_addr));
        PRINTF("udp data len:%d \n udp data: %s\r\n",recvBytes, buf);
        if(strstr(buf,"smarthic")!=NULL)
        {
            PRINTF("got it start get item\r\n");
            uint8 resp[16] = "shutdown";
            uint8 temp[8] = {0};
            uint32 value;
            memcpy(temp, buf+9, 8);
            sscanf(temp,"%x",&value);
            //gHicID = swapInt32(value);
            PRINTF("ghic value:%x\r\n", value);
            gHicID = value ^ DECRYPTION_CONSTANT;
            memset(temp, 0, 8);
            memcpy(temp, buf+9+8, 8);
            sscanf(temp,"%x",&value);
            PRINTF("guser value:%x\r\n", value);
            //gUserId = swapInt32(value);
            gUserId = value ^ DECRYPTION_CONSTANT;
            PRINTF("\nuserId[%d] hicId[%d]\n",gUserId,gHicID);
            sendto(sockfd, resp, strlen(resp), 0, (struct sockaddr *)&clientAddr, addrLen);     
            break;          
        }
    }
    close(sockfd);
    pthread_exit(NULL);    
}

