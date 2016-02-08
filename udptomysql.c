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

char decode_mic_lat(char c) {
	if(c>='0' && c<='9') return c;
	if(c>='A' && c<='J') return c-'A'+'0';
	if(c>='K' && c<='L') return ' ';
	if(c>='P' && c<='Y') return c-'P'+'0';
	if(c=='Z') return ' ';
	return ' ';
}

int checkcall(char*call) {
	char *p;
	if(strlen(call)<5) return 0;
	p=call;
	if(*p!='B') return 0;
	if(!isdigit(*(p+2))) return 0;
	while(*p) {
		if( isupper(*p) ||
		    isdigit(*p) ||
		    (*p=='-') 
		) p++;
		else return 0;
	}
	return 1;
}
void ToMysql(char *buf, int len)
{	char bufcopy[MAXLEN],sqlbuf[MAXLEN],*end;
	if(len<=10) return;
	if(len>1000)len=1000;
	if(buf[len-1]=='\n') len--;
	if(buf[len-1]=='\r') len--;
	buf[len]=0;
	strcpy(bufcopy,buf);

	char *call="", *path="", datatype=0, *lat="", *lon="", table=0, symbol=0, *msg="", *p,*s;
	call = buf;
	p=strchr(buf,'>');
	if(p==NULL) return;
	*p=0;
	if(checkcall(call)==0) {
#ifdef DEBUG
		fprintf(stderr,"skipp call: %s\n",call);
#endif	
		return;
	}
	s=p+1;
	path=s;
        p = strchr(s,':');
	if(p==NULL) return;
	p++;
	datatype = *p;
	p++;

	if( datatype == '/' ) {  // change datatype / to !
		datatype = '!'; 
		if( strlen(p)<17 ) 
			goto unknow_msg;
		p+=7;
	} else if( datatype == '@' ) {  // change datatype @ to =
		datatype = '='; 
		if( strlen(p)<17 ) 
			goto unknow_msg;
		p+=7;
	}
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
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,bufcopy,len);
		end = my_stpcpy(end,"')");
		*end=0;
		fprintf(stderr,"%s\n",sqlbuf);
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			fprintf(stderr, "Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		

		end = my_stpcpy(sqlbuf,"REPLACE INTO lastpacket(tm,`call`,datatype,lat,lon,`table`,symbol,msg) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"')");
		*end=0;
		fprintf(stderr,"%s\n",sqlbuf);
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			fprintf(stderr, "Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		return;
	}

	if( (datatype == '`') ) {    // Mic-E
		if( strlen(p)<8 ) 
			goto unknow_msg;
		if( strlen(path)<6 )
			goto unknow_msg;
		char lat[9];
		lat[0]=decode_mic_lat(*(path));
		lat[1]=decode_mic_lat(*(path+1));
		lat[2]=decode_mic_lat(*(path+2));
		lat[3]=decode_mic_lat(*(path+3));
		lat[4]='.';
		lat[5]=decode_mic_lat(*(path+4));
		lat[6]=decode_mic_lat(*(path+5));
		char c;
		c = *(path+3);
		if ( c>='0' && c<='9' ) lat[7]='S';
		else lat[7]='N';
		lat[8]=0;

		int d;
		d = *p - 28;
		if (*(path+4)>='P' ) d+=100;
		if( d>=180 && d<=189)
			d-=80;
		else if( d>=190 && d<=199)
			d-=190;
		int m;
		m = *(p+1) - 28;
		if(m>=60) m-=60;
	
		int hm;
		hm = *(p+2) - 28;
		
		char lon[10];
		lon[0] = (d/100) + '0';	
		lon[1] =  (d%100)/10 + '0';
		lon[2] =  (d%10) + '0';
		lon[3] = m/10 + '0';
		lon[4] = m%10 + '0';
		lon[5]='.';
		lon[6]=hm/10 + '0';
		lon[7]=hm%10 + '0';
		if(*(path+5)>='P') lon[8]='W';	
		else lon[8]='E';
		lon[9]=0;

		msg=p;
		table=*(p+7);
		symbol = *(p+6);
		p+=8;
		end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,bufcopy,len);
		end = my_stpcpy(end,"')");
		*end=0;
		fprintf(stderr,"%s\n",sqlbuf);
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			fprintf(stderr, "Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		} 
		end = my_stpcpy(sqlbuf,"REPLACE INTO lastpacket(tm,`call`,datatype,lat,lon,`table`,symbol,msg) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"')");
		*end=0;
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
	*end=0;

	fprintf(stderr,"%s\n",sqlbuf);
	if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   		fprintf(stderr, "Failed to insert row, Error: %s\n",
           	mysql_error(mysql));
	}
}
void Process(int u_fd) 
{	
	char buff[MAXLEN];
	int n;

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
