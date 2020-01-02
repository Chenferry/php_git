#ifndef  _CONNECT_H
#define  _CONNECT_H
#include "board.h"

extern uint16 user_msg_send(uint8 *data, uint16 len);
extern uint8 user_socket_create(uint8 *host, uint16 port, uint8 isSSL);
extern uint8 user_create_app_conn(uint8 *host, uint16 port, uint8 isSSL);
#endif