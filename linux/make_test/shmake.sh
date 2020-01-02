#!/bin/bash
echo "start shell"

export PATH="$PATH:/cc/cc"
INTVALUE=1
if [ $INTVALUE -eq 1 ];then
	echo "i am 1"
	mkdir cc
else
	echo "i am not 1"
fi
/mnt/hgfs/work/linux/make_test/main &
#make
