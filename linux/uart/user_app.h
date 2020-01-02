#ifndef  _USER_APP_H
#define  _USER_APP_H
#include "board.h"
extern uint8 g_mac_str[16];
extern uint8 g_sta_mac[8];

extern void *user_uart_recv(void *arg);
extern void user_get_item_addr();
extern uint8 user_http_callback_handle(uint8 *pMsg);
#endif