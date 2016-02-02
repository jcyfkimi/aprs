#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <time.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <linux/if.h>
#include <linux/if_ether.h>
#include <linux/if_packet.h>
#include <ctype.h>
#include "sock.h"

#define DEBUG 1

#define MAXLEN 16384

#define DBUSER "root"
#define DBPASSWD ""
#define DB     "aprs"
#define DBHOST "localhost"
#define DBPORT 3306
#define DBSOCKPATH "/var/lib/mysql/mysql.sock"

#include <mysql/mysql.h>
MYSQL *mysql;

MYSQL * connectdb(void)
{
        MYSQL *mysql;

        if ((mysql=mysql_init(NULL))==NULL) {
                fprintf(stderr,"mysql_init error\n");
                exit(1);
        }
        if( mysql_real_connect(mysql, DBHOST, DBUSER, DBPASSWD,
        DB, DBPORT, DBSOCKPATH, 0)== NULL)
        {
                fprintf(stderr,"mysql_init error\n");
                exit(1);
        }
        return mysql;
}

char *my_stpcpy(char *dst, const char *src){
	char *q = dst;
	const char *p = src;
	while (*p) *q++ = *p++; 
	return q; 
} 

void ToMysql(char *buf, int len)
{	char bufcopy[MAXLEN],sqlbuf[MAXLEN],*end;
	if(len<=10) return;
	if(len>1000)len=1000;
	if(buf[len-1]=='\n') len--;
	if(buf[len-1]=='\r') len--;
	buf[len]=0;
	strcpy(bufcopy,buf);

	char *call="", datatype=0, *lat="", *lon="", table=0, symbol=0, *msg="", *p,*s;
	call = buf;
	p=strchr(buf,'>');
	if(p==NULL) return;
	*p=0;
	s=p+1;
        p = strchr(s,':');
	if(p==NULL) return;
	p++;
	datatype = *p;
	p++;
	if( (datatype == '=') || (datatype=='!') ) {
		if( strlen(p)<17 ) 
			goto unknow_msg;
		lat = p;
		table=*(p+8);
		*(p+8)=0;
		p+=9;
		lon= p;
		symbol = *(p+9);
		*(p+9)=0;
		p+=10;
		msg=p;
		end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		*end = table; end++;
		end = my_stpcpy(end,"','");
		*end = symbol; end++;
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,bufcopy,len);
		end = my_stpcpy(end,"')");
		fprintf(stderr,"%s\n",sqlbuf);
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			fprintf(stderr, "Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		return;
	}

unknow_msg:	
	end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`, raw) VALUES(now(),'");
	end += mysql_real_escape_string(mysql,end,call,strlen(call));
	end = my_stpcpy(end,"','");
	end += mysql_real_escape_string(mysql,end,bufcopy,len);
	end = my_stpcpy(end,"')");

	fprintf(stderr,"%s\n",sqlbuf);
	if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   		fprintf(stderr, "Failed to insert row, Error: %s\n",
           	mysql_error(mysql));
	}
}
void Process(int u_fd) 
{	
	char buff[MAXLEN];
	int m,n;

	while (1) {
		n = recv (u_fd, buff, MAXLEN,0);
		if(n<0)   {
			err_sys("recv get %d from udp client\n",n);
			exit(0);		
		}
		if(n==0) continue;
		buff[n]=0;
#ifdef DEBUG
		fprintf(stderr,"C: %s",buff);
#endif
		ToMysql(buff, n);
	}
}

int main(int argc, char *argv[])
{
	int u_fd;
	int llen;
	char buf[MAXLEN];

	signal(SIGCHLD,SIG_IGN);

	if(argc!=1) {
		fprintf(stderr,"usage:  udptomysql\n");
		exit(0);
	}

#ifndef DEBUG
	daemon_init("udptomysql",LOG_DAEMON);
	while(1) {
   		int pid;
   		pid=fork();
   		if(pid==0) // i am child, will do the job
			break;
   		else if(pid==-1) // error
   			exit(0);
   		else
   			wait(NULL); // i am parent, wait for child
   		sleep(2);  // if child exit, wait 2 second, and rerun
   	}
#endif
	err_msg("starting\n");
	u_fd = Udp_server("127.0.0.1","14583",(socklen_t *)&llen);

#ifdef DEBUG
	fprintf(stderr,"C: %s",buf);
	fprintf(stderr,"u_fd=%d\n",u_fd);
#endif
	mysql=connectdb();	
	Process(u_fd);
	return 0;
}
