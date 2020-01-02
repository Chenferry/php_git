#include "header.h"
uint32 gHicID;
uint32 gUserId;

void *user_listen_brst(void *arg)
{
    sint16 ret;
    int sockfd;
    struct sockaddr_in server_addr;
    struct sockaddr_in client_addr;
    uint8 msg[128];

    os_printf("into user_listen_brst\n");
    sockfd = socket(PF_INET, SOCK_DGRAM, 0);
    if (sockfd < 0) {
        os_printf("socket create failed\n");
        vTaskDelete(NULL);
        return;
    }
    sint16 flag = 1;
    setsockopt(sockfd, SOL_SOCKET, SO_BROADCAST|SO_REUSEADDR, &flag, sizeof(sint16));

    memset(&server_addr, 0, sizeof(server_addr));
    server_addr.sin_family = AF_INET;
    server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
    server_addr.sin_port = htons(BROADCAST_LISTEN_PORT);

    ret = bind(sockfd, (struct sockaddr *)&server_addr, sizeof(server_addr));
    if (-1 >= ret)
    {
        os_printf("socket bind failed ret[%d]\n",ret);
        vTaskDelete(NULL);
        return;
    }

    socklen_t addr_len = sizeof(struct sockaddr_in);
    os_printf("user_listen_brst socket ok ready to recv\n");
    while(1)
    {
        ret = recvfrom(sockfd, msg, 128, 0, (struct sockaddr *)&client_addr, &addr_len);
        if (ret > 0)
        {
            os_printf("recv msg:%s from %s\n", msg, inet_ntoa(client_addr.sin_addr));
            if (NULL != strstr(msg, "smarthic:"))
            {
                if ((0 != gHicID && 0xffffffff != gHicID) && (0 != gUserId && 0xffffffff != gUserId))
                {
                    continue;
                }
                uint8 resp[16] = "shutdown";
                uint8 temp[8] = {0};
                uint32 value;
                memcpy(temp, msg+9, 8);
                sscanf(temp,"%x",&value);
                //gHicID = swapInt32(value);
                gHicID = value ^ DECRYPTION_CONSTANT;
                memset(temp, 0, 8);
                memcpy(temp, msg+9+8, 8);
                sscanf(temp,"%x",&value);
                //gUserId = swapInt32(value);
                gUserId = value ^ DECRYPTION_CONSTANT;
                os_printf("\nuserId[%d] hicId[%d]\n",gUserId,gHicID);
                //sendto(sockfd, resp, strlen(resp), 0, (struct sockaddr *)&client_addr, addr_len);
                user_get_item_addr();
            }
            else if (NULL != strstr(msg, "apcfg:"))
            {// ap+sta模式广播方式配置ssid和pwd
                if ((0 != gHicID && 0xffffffff != gHicID) && (0 != gUserId && 0xffffffff != gUserId))
                {
                    continue;
                }
                //g_bcast_flag = 1;
                uint8 resp[16] = "shutdown";
                uint8 ssid[32] = {0};
                uint8 pwd[64] = {0};
                uint8 temp[8] = {0};
                char *endptr;
                uint32 value;
                uint8 ssidLen;
                uint8 pwdLen;

                memcpy(temp, msg+6, 8);
                sscanf(temp,"%x",&value);
                // value = strtol(temp, &endptr, 16);
                //gHicID = swapInt32(value);
                gHicID = value ^ DECRYPTION_CONSTANT;
                memset(temp, 0, 8);
                memcpy(temp, msg+6+8, 8);
                sscanf(temp,"%x",&value);
                // value = strtol(temp, &endptr, 16);
                //gUserId = swapInt32(value);
                gUserId = value ^ DECRYPTION_CONSTANT;
                // memcpy(ssid, msg+6+16, 32);
                // memcpy(pwd, msg+6+16+32, 64);
                // ssidLen = (0xf & msg[6+16]) << 8 + 0xf & msg[6+16+1];
                memset(temp, 0, 8);
                memcpy(temp, msg+6+16, 2);
                // sscanf(temp,"%x",&ssidLen);
                ssidLen = strtol(temp, &endptr, 16);

                memcpy(ssid, msg+6+16+2, ssidLen);
                memset(temp, 0, 8);
                memcpy(temp, msg+6+16+2+ssidLen, 2);
                // sscanf(temp,"%x",&pwdLen);
                pwdLen = strtol(temp, &endptr, 16);

                memcpy(pwd, msg+6+16+2+ssidLen+2, pwdLen);
                os_printf("ssidLen[%x] pwdLen[%x] userId[%d] hicId[%d]\n",ssidLen,pwdLen,gUserId,gHicID);

                os_printf("\nssid[%s] pwd[%s] userId[%d] hicId[%d]\n",ssid,pwd,gUserId,gHicID);

                // sendto(sockfd, resp, strlen(resp), 0, (struct sockaddr *)&client_addr, addr_len);
                // TODO config wifi

                user_get_item_addr();
                // break;
            }
            else if (NULL != strstr(msg, "isLocal"))
            {
                uint8 resp[16] = "true";
                //user_send_brst(resp);
                // sendto(sockfd, resp, strlen(resp), 0, (struct sockaddr *)&client_addr, addr_len);
            }
        }
    }

    close(sockfd);

    os_printf("out user_listen_brst\n");
    vTaskDelete(NULL);
    return;
}
