/* ------------------------------------------------------------------------------------------------
 * 此文件必须存在，且必须包含以下内容，已存在则需添加以下内容 ，否则需要自己创�?
 * ------------------------------------------------------------------------------------------------
 */
#ifndef HAL_BOARD_H
#define HAL_BOARD_H

// #include "c_types.h"

/* ------------------------------------------------------------------------------------------------
 *                                             Macros
 * ------------------------------------------------------------------------------------------------
 */

#ifndef BV
#define BV(n)      (1 << (n))
#endif

/* ------------------------------------------------------------------------------------------------
 *                                               Types
 * ------------------------------------------------------------------------------------------------
 */
 typedef signed   char   int8;
 typedef signed   char   sint8;
 typedef unsigned char   uint8;

typedef signed   short  int16;
typedef signed   short  sint16;
typedef unsigned short  uint16;

typedef signed   long   int32;
typedef signed   long   sint32;
typedef unsigned long   uint32;

//typedef unsigned char   bool;

/* ------------------------------------------------------------------------------------------------
 *                                        Standard Defines
 * ------------------------------------------------------------------------------------------------
 */
#ifndef TRUE
#define TRUE 1
#endif

#ifndef FALSE
#define FALSE 0
#endif
/*
#ifndef NULL
#define NULL 0
#endif
*/

/*********************************************************************
 * CONSTANTS
 */

#ifndef false
  #define false 0
#endif

#ifndef true
  #define true 1
#endif

#ifndef CONST
  #define CONST const
#endif

#ifndef GENERIC
  #define GENERIC
#endif

/*** Generic Status Return Values ***/
#define SUCCESS                   0x00
#define FAILURE                   0x01

/* This is the aligned version of ip_addr_t,
   used as local variable, on the stack, etc. */
struct ip_addr {
  uint32 addr;
};

typedef struct ip_addr ip_addr_t;
typedef struct ip_addr_packed ip_addr_p_t;

#endif