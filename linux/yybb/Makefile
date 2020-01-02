#source file
# 源文件，自动找所有 .c 和 .cpp 文件，并将目标定义为同名 .o 文件
SOURCE  := $(wildcard *.c) $(wildcard *.cpp) 

OBJS    := $(patsubst %.c,%.o,$(patsubst %.cpp,%.o,$(SOURCE)))
 
#target you can change test to what you want
# 目标文件名，输入任意你想要的执行文件名
TARGET  := yybbtest
 
#compile and lib parameter
# 编译参数
CC      := gcc
#CCC		:= arm-hisiv100nptl-linux-g++

LIBS    := -lm
LDFLAGS:= 
DEFINES:= -DDEBUG
INCLUDE:= -I ./include   	

CFLAGS  := -g -Wall -O2 -Wl,-gc-sections -DHI_OS_LINUX -lrt -pthread $(DEFINES) $(INCLUDE)
CFLAGS  += "-fno-strict-aliasing"


 
 
#i think you should do anything here
# 下面的基本上不需要做任何改动了
.PHONY : everything objs clean veryclean rebuild prepare
 
everything : $(TARGET)
 
all : $(TARGET)
 
objs : $(OBJS)
 
rebuild: veryclean everything
               
clean :
	rm -fr *.o
	rm -fr $(TARGET)
   
veryclean : clean
	rm -fr $(TARGET)

 
$(TARGET) : $(OBJS) 
	$(CC) $(CFLAGS) -o $@ $(OBJS) $(LDFLAGS) $(LIBS)
