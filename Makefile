all: aprstcp aprsudp udptoaprs udptomysql udptolog aprs.fi.toudp local.toudp gt02

aprs.fi.toudp: aprs.fi.toudp.c passcode.c
	gcc -o aprs.fi.toudp aprs.fi.toudp.c -Wall
local.toudp: local.toudp.c passcode.c
	gcc -o local.toudp local.toudp.c -Wall
aprstcp: aprstcp.c
	gcc  -o aprstcp aprstcp.c	 -Wall
gt02: gt02.c
	gcc -g -o gt02 gt02.c	 -Wall -lm
aprsudp: aprsudp.c
	gcc -g -o aprsudp aprsudp.c	 -Wall
udptoaprs: udptoaprs.c passcode.c
	gcc -o udptoaprs udptoaprs.c -Wall
udptomysql: udptomysql.c
	gcc -g -o udptomysql udptomysql.c -Wall -lmysqlclient -L/usr/lib64/mysql/

clean:
	rm -f aprsudp.o
