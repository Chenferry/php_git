#ifndef __USER_TCP_H__
#define __USER_TCP_H__

#ifdef __cplusplus
extern "C" 
{
#endif

#include "typedef_board.h"

void tcp_check_connect(void);

void tcp_msg_rec(void);

int get_mac(unsigned char *mac);
#ifdef __cplusplus
}
#endif
#endif