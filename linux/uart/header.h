#ifndef __HEADER_TEST__
#define __HEADER_TEST__

#include <stdlib.h>
#include <stdio.h>
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

#include "board.h"
#include "post.h"
#include "udp_listen.h"
#include "connect.h"
#include "cJSON.h"
#include "user_app.h"

#define PRINTF(...) os_printf(__VA_ARGS__)
#define os_printf(fmt, ...) printf(fmt, ##__VA_ARGS__)
#define os_malloc(size) malloc(size)

#define portTICK_RATE_MS (1000)
#define vTaskDelay(seconds) sleep(seconds)

#define vTaskDelete(taskhandle)

#endif