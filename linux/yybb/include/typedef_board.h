#ifndef __TYPEDEF_BOARD_H__
#define __TYPEDEF_BOARD_H__

#ifdef __cplusplus
extern "C" 
{
#endif

#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <errno.h>
#include <string.h>
#include <unistd.h>
#include <fcntl.h>
#include <netdb.h>
#include <sys/socket.h>
#include <netinet/ether.h> 
#include <netinet/in.h>
#include <net/if.h> 
#include <netinet/tcp.h>
#include <sys/types.h>
#include <arpa/inet.h>
#include <pthread.h>
#include <sys/time.h>
#include <time.h>
#include <signal.h>
#include <sys/ioctl.h> 
#include <linux/netlink.h> 
#include <linux/rtnetlink.h> 
#include <sys/stat.h> 

#include "cJSON.h"
#include "inirw.h"
#include "md5.h"

#include "user_main.h"
#include "user_flash.h"
#include "user_udp.h"
#include "user_tcp.h"

#ifndef uint8
    typedef unsigned char uint8;
#endif
#ifndef int8
    typedef signed char int8;
#endif
#ifndef uint16
    typedef unsigned short uint16;
#endif
#ifndef int16
    typedef signed short int16;
#endif
#ifndef uint32
    typedef unsigned int uint32;
#endif
#ifndef int32
    typedef signed int int32;
#endif

#ifndef NULL
    typedef (void *)0 NULL;
#endif

#if(defined DEBUG)
#define PRINTF(...) printf(__VA_ARGS__)
#else
#define PRINTF(...)
#endif

#ifdef __cplusplus
}
#endif
#endif