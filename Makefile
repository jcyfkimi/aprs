all: aprstcp aprsudp udptoaprs udptomysql udptolog
aprstcp: aprstcp.c
	gcc  -o aprstcp aprstcp.c	 -Wall
aprsudp: aprsudp.c
	gcc  -o aprsudp aprsudp.c	 -Wall
udptoaprs: udptoaprs.c
	gcc -o udptoaprs udptoaprs.c -Wall
udptomysql: udptomysql.c
	gcc -o udptomysql udptomysql.c -Wall -lmysqlclient -L/usr/lib64/mysql/

clean:
	rm -f aprsudp.o
