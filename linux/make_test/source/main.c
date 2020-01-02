#include <stdio.h>
#include <string.h>
#include "add.h"
#include "inirw.h"
#include <signal.h>
#include <sys/time.h>

void sig_handler(int msg)
{    
    printf("aaa\n");
    switch(msg)        
    {                
        case SIGALRM:                        
            //g_timeout = 1;
            printf("signal ALARM\n");
            break;  
        case SIGPIPE:
            //g_disconnect = 1;
            printf("signal PIPE\n");
        default:                        
            break;        
    }        

    return;
}
void sig2_handler(int msg)
{    
    printf("aaa\n");
    switch(msg)        
    {                
        case SIGALRM:                        
            //g_timeout = 1;
            printf("signal2 ALARM\n");
            break;  
        case SIGPIPE:
            //g_disconnect = 1;
            printf("signal PIPE\n");
        default:                        
            break;        
    }        

    return;
}
int main()
{
	int a=10;
	int b=20;
	char c[]="1234";
	char *d="123gttaaaaaaaat4";
	char *filename="test.ini";
	
	const char *act_sect = "dev_act";
	char *actFlag    = "act_flag";
        char *actValue   = "act_value";
       unsigned char flag[12] = "ACTIVE__KEY:";

	const char *act_sect1= "dev_act1";
	char *actFlag1    = "act_flag1";
        char *actValue1   = "act_value1";
        unsigned char flag1[12] = "ACTIVE__KEY2:";
       long src=334455;
	printf("sizeof(c):%d\n",sizeof(c));
	printf("sizeof(d):%d\n",sizeof(d));
	printf("strlen(c)%d\n", strlen(c));
	printf("strlen(d)%d\n", strlen(d));
	printf("%d\n", add(a,b));
	system("touch test.ini");
	iniFileLoad(filename);
	iniSetString(act_sect1, actFlag1, flag1);
	iniFileLoad(filename);
	iniSetString(act_sect, actFlag, flag);
	iniSetInt(act_sect, actValue, src, 10);
	while(1)
	{
	}
	return 0;
}
