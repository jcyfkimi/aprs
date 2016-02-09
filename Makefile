all: aprstcp aprsudp udptoaprs udptomysql udptolog aprs.fi.toudp

aprs.fi.toudp: aprs.fi.toudp.c passcode.c
	gcc -o aprs.fi.toudp aprs.fi.toudp.c -Wall
aprstcp: aprstcp.c
	gcc  -o aprstcp aprstcp.c	 -Wall
aprsudp: aprsudp.c
	gcc  -o aprsudp aprsudp.c	 -Wall
udptoaprs: udptoaprs.c passcode.c
	gcc -o udptoaprs udptoaprs.c -Wall
udptomysql: udptomysql.c
	gcc -g -o udptomysql udptomysql.c -Wall -lmysqlclient -L/usr/lib64/mysql/

clean:
	rm -f aprsudp.o
