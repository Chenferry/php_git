#include "typedef_board.h"

#define ALIGN_LEN (4)
#define GADDFLAG_ID (0)
#define GADDFLAG_ID_LEN (1)
#define HIC_ID (GADDFLAG_ID+GADDFLAG_ID_LEN+3)
#define HIC_ID_LEN (4)
#define PARAM_LEN 100
#define MAX_FILE_LEN 1024
uint32 user_param[PARAM_LEN];

const char *g_cfg_file = "/tmp/yybb/config_wifiex.ini";
const char *g_act_file = "/tmp/yybb/config_active.ini";
const char *g_param_file = "/tmp/yybb/param.init";
const char *g_test_file = "/tmp/yybb1/cc.init";
int8 user_flash_write(uint8 addr, uint8 len, void *buf);
int8 user_flash_read(uint8 addr, uint8 len, void *buf);

void user_flash_init(void)
{
    uint8 test=123;
    user_flash_write(GADDFLAG_ID, GADDFLAG_ID_LEN, &test);
    sleep(1);
    user_flash_read(0, 0, NULL);
}

int8 user_flash_write(uint8 addr, uint8 len, void *buf)
{
    FILE *filefd;
    int ret;
    uint8 offset=0;
    //memcpy(user_param, buf, addr/ALIGN_LEN);
    
    user_param[offset++]=0x11;
    user_param[offset++]=0x11211;
    user_param[offset++]=0x11122;
    user_param[offset++]=0x112;
    user_param[offset++]=0x113;
  
    filefd = fopen(g_param_file, "wb");
    if(filefd == NULL)
    {
        PRINTF("file open wrong Error:%s",strerror(errno));
        return 0;
    }
    PRINTF("sizeof(user_param):%d\r\n", sizeof(user_param));
    ret = fwrite(user_param, 1, sizeof(user_param), filefd);
    PRINTF("fwrite bytes:%d\r\n", ret);
    fclose(filefd);
    return 0;
}

int8 user_flash_read(uint8 addr, uint8 len, void *buf)
{
    FILE *filefd;
    int ret;
    int i;
    filefd = fopen(g_param_file, "rb");
    if(filefd == NULL)
    {
        PRINTF("file open wrong Error:%s",strerror(errno));
        return 0;
    }
    PRINTF("sizeof(user_param):%d\r\n", sizeof(user_param));
    ret = fread(user_param, 1, sizeof(user_param), filefd);
    PRINTF("fread bytes:%d\r\n", ret);
    fclose(filefd);   
     for(i=0; i<PARAM_LEN; i++)
    {
        PRINTF("%x", user_param[i]);
    }   
    return 0;
}
