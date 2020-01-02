#ifndef  _UDP_LISTEN_H
#define  _UDP_LISTEN_H
#define BROADCAST_LISTEN_PORT (9301)
#define DECRYPTION_CONSTANT (0xAABBCCDD)

extern uint32 gHicID;
extern uint32 gUserId;

extern void *user_listen_brst(void *arg);

#endif