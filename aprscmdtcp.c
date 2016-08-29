/* aprstcp v1.0 by  james@ustc.edu.cn 2015.12.19

   replay 14580 tcp aprs packet to "china.aprs2.net"
   send all packets to udp
	127.0.0.1 14582
	127.0.0.1 14583
	120.25.100.30 14580
   send packets with "-13" to 114.55.54.60 14580
*/

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <time.h>
#include <string.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <linux/if.h>
#include <linux/if_ether.h>
#include <linux/if_packet.h>
#include "sock.h"
#include <ctype.h>

#include "db.h"

#define MAXLEN 16384

// #define DEBUG 1

static char mycall[20];
char *laddr,*lport,*raddr,*rport;
unsigned long fwd,rfwd;
int r_fd, c_fd;
char scaddr[MAXLEN],sladdr[MAXLEN],sraddr[MAXLEN],srcaddr[MAXLEN];

char * PrintAddr(struct sockaddr *sa)
{
	struct sockaddr_in *sa_in;
	struct sockaddr_in6 *sa_in6;
	static char buf[MAXLEN];
	char buf2[MAXLEN];

	if(sa->sa_family==AF_INET) {
		sa_in = (struct sockaddr_in*)sa;
		snprintf(buf,MAXLEN,"%s:%d",
			inet_ntop(sa_in->sin_family,&sa_in->sin_addr,buf2,MAXLEN),
			ntohs(sa_in->sin_port));
	} else if(sa->sa_family==AF_INET6) {
		sa_in6 = (struct sockaddr_in6 *)sa;
		snprintf(buf,MAXLEN,"%s:%d",
			inet_ntop(sa_in6->sin6_family,&sa_in6->sin6_addr,buf2,MAXLEN),
			ntohs(sa_in6->sin6_port));
	} else snprintf(buf,MAXLEN,"unknow family %d",sa->sa_family);
	return buf;
}

void PrintStats(void)
{
	syslog(LOG_INFO,"%s->%s ",scaddr,sladdr);
	syslog(LOG_INFO,"==> %s->%s\n",srcaddr,sraddr);
	syslog(LOG_INFO,"===> %8lu bytes\n",fwd);
	syslog(LOG_INFO,"<=== %8lu bytes\n",rfwd);

}
void sendudp(char*buf, int len, char *host, int port)
{
        struct sockaddr_in si_other;
        int s, slen=sizeof(si_other);
        int l;
#ifdef DEBUG
        fprintf(stderr,"send to %s,",host);
#endif
        if ((s=socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP))==-1) {
                fprintf(stderr,"socket error");
                return;
        }
        memset((char *) &si_other, 0, sizeof(si_other));
        si_other.sin_family = AF_INET;
        si_other.sin_port = htons(port);
        if (inet_aton(host, &si_other.sin_addr)==0) {
                fprintf(stderr, "inet_aton() failed\n");
                close(s);
                return;
        }
        l = sendto(s, buf, len, 0, (const struct sockaddr *)&si_other, slen);
#ifdef DEBUG
        fprintf(stderr,"%d\n",l);
#endif
        close(s);
}

void insertIG(char *buf, int *len) {
        char *p;
        if(buf[*len-1]=='\n')
                (*len)--;
        if(buf[*len-1]=='\r')
                (*len)--;
        if(buf[*len-1]=='\n')
                (*len)--;
        buf[*len]=0;

	if( (strncmp(buf,"USER ",5)==0)
	  ||(strncmp(buf,"user ",5)==0) ) {
		p=buf+5;
		int i=0;
		while((p<buf+*len) && (*p!=' ')) {
			mycall[i]=toupper(*p);
			if(i==10) break;
			i++;
			p++;
		}
		mycall[i]=0;
#ifdef DEBUG
	fprintf(stderr,"get icall %s\n",mycall);
#endif
		return;
	}

	if(mycall[0]) {
		if(buf[strlen(mycall)]=='>') {
			if(strncasecmp(buf,mycall,strlen(mycall))==0) return;
		}
        	p=buf+*len;
		*len+=snprintf(p,15,"/IG:%s",mycall);
#ifdef DEBUG
	fprintf(stderr,"from igate %s\n",buf);
#endif
	}
}


void relayaprs(char *buf, int len)
{	char mybuf[MAXLEN];
	strncpy(mybuf,buf,len);
        sendudp(mybuf,len, "120.25.100.30",14580);   // forward to aprs.hellocq.net
      	if(strstr(mybuf,"-13>"))
                  sendudp(mybuf, len , "114.55.54.60",14580);   // forward -13 to lewei50.comI
	insertIG(mybuf, &len) ;
        sendudp(mybuf,len, "127.0.0.1",14582);   // udptolog
        sendudp(mybuf,len, "127.0.0.1",14583);   // udptomysql
}

char *my_stpcpy(char *dst, const char *src){
        char *q = dst;
        const char *p = src;
        while (*p) *q++ = *p++;
        return q;
}


void	got_cmd_reply(char *buf, int len)
{	char mybuf[MAXLEN];
	char sqlbuf[MAXLEN];
	MYSQL_RES *result;
	MYSQL_ROW row;
	if(mycall[0]==0) return;
	strncpy(mybuf,buf,len);
        if(mybuf[len-1]=='\n')
                len--;
        if(mybuf[len-1]=='\r')
                len--;
        if(mybuf[len-1]=='\n')
                len--;
	mybuf[len]=0;
	snprintf(sqlbuf,MAXLEN,"select id from ykcmd where `call`=\"%s\" and TIMESTAMPDIFF(second,sendtm,now())<=30 and replytm=\"0000-00-00 00:00:00\" order by sendtm limit 1",mycall);
	if(mysql_query(mysql,sqlbuf)!=0) {
#ifdef DEBUG
	fprintf(stderr,"sql %s error\n",sqlbuf);
#endif
		return;
	}
	result = mysql_store_result(mysql);
    	if (result)  // there are rows
    	{	
		row = mysql_fetch_row(result);
		if(row) {
			char *end;
			end =  my_stpcpy(sqlbuf,"update ykcmd set replytm=now(), reply=\"");
			end += mysql_real_escape_string(mysql,end,mybuf,strlen(mybuf));
			end = my_stpcpy(end,"\" where id=");
			end = my_stpcpy(end,row[0]);
			mysql_free_result(result);
#ifdef DEBUG
	fprintf(stderr,"sql %s \n",sqlbuf);
#endif
			if(mysql_real_query(mysql,sqlbuf,end-sqlbuf)!=0) {
#ifdef DEBUG
	fprintf(stderr,"sql %s error\n",sqlbuf);
#endif
				return;
			}
			
		} else
			mysql_free_result(result);
	}

}

void	got_new_cmd(char *buf, int len)
{	char mybuf[MAXLEN];
	char sqlbuf[MAXLEN];
	if(mycall[0]==0) return; // not login, return
	char *p, *dcall, *sn, *pass, *cmd;
	strncpy(mybuf,buf,len);
        if(mybuf[len-1]=='\n')
                len--;
        if(mybuf[len-1]=='\r')
                len--;
        if(mybuf[len-1]=='\n')
                len--;
	mybuf[len]=0;
	if(mybuf[0]!='$') return;
	dcall = mybuf+1;
	p = dcall;
	while(*p && *p!=',') p++;
	if(*p!=',') return;
	*p=0; p++; sn=p;
	while(*p && *p!=',') p++;
	if(*p!=',') return;
	*p=0; p++; pass=p;
	while(*p && *p!=',') p++;
	if(*p!=',') return;
	*p=0; p++; cmd=p;
	char *end;
	end =  my_stpcpy(sqlbuf,"insert into ykcmd (cmdtm,`call`,sn,pass,cmd,sendtm,replytm) values (now(),\"");
	end += mysql_real_escape_string(mysql,end,dcall,strlen(dcall));
	end = my_stpcpy(end,"\",\"");
	end += mysql_real_escape_string(mysql,end,sn,strlen(sn));
	end = my_stpcpy(end,"\",\"");
	end += mysql_real_escape_string(mysql,end,pass,strlen(pass));
	end = my_stpcpy(end,"\",\"");
	end += mysql_real_escape_string(mysql,end,cmd,strlen(cmd));
	end = my_stpcpy(end,"\",\"0000-00-00 00:00:00\",\"0000-00-00 00:00:00\")");
#ifdef DEBUG
	fprintf(stderr,"sql %s \n",sqlbuf);
#endif
	if(mysql_real_query(mysql,sqlbuf,end-sqlbuf)!=0) {
#ifdef DEBUG
		fprintf(stderr,"sql %s error\n",sqlbuf);
#endif
	}
}
void Process(int c_fd) 
{	
	fd_set rset;
	struct timeval tv;
	int m,n;
	int max_fd;
	struct sockaddr_in6 sa;
	int salen;
	fwd=rfwd=0;	
        char sqlbuf[MAXLEN];
	MYSQL_RES *result;
	MYSQL_ROW row;

	r_fd= Tcp_connect(raddr,rport);
	
	scaddr[0]=sladdr[0]=sraddr[0]=srcaddr[0]=0;
	salen=sizeof(sa);
	if(getpeername(c_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(scaddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	salen=sizeof(sa);
	if(getsockname(c_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(sladdr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	
	salen=sizeof(sa);
	if(getpeername(r_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(sraddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	salen=sizeof(sa);
	if(getsockname(r_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(srcaddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);

	while (1) {
		FD_ZERO(&rset);
		FD_SET(c_fd, &rset);
		FD_SET(r_fd, &rset);
		max_fd = max(c_fd,r_fd);
		tv.tv_sec = 1;
		tv.tv_usec = 0;

		m = Select (max_fd + 1, &rset, NULL, NULL, &tv);

		// if (m == 0) continue;
		
		if (FD_ISSET(r_fd, &rset)) {
			char buffer[MAXLEN];
			n = recv (r_fd, buffer, MAXLEN,0);
			if(n<=0)   {
				PrintStats();
				exit(0);
			}
			if( strstr(buffer,"# javAPRSSrvr") &&
			    strstr(buffer,"T2CHINA 221.231.138.178:14580")) {
				static time_t lastkeep=0;
				time_t curt=time(NULL);
				if( (lastkeep!=0) && (curt-lastkeep < 60*10))	
					continue;
				lastkeep=curt;
			}
			Write(c_fd, buffer, n);
			rfwd+=n;
		}	
		if (FD_ISSET(c_fd, &rset)) {
			static char buffer[MAXLEN];
			static int lastread =0;
			n = recv (c_fd, buffer+lastread, MAXLEN-lastread-1-25,0);
			if(n<=0)   {
				PrintStats();
				exit(0);		
			}
			buffer[lastread+n]=0;
			fwd+=n;
			char *p,*s;
			n=lastread+n;
			p=buffer;
			while (1) {
				if((p-buffer) >= n) break;
				s=strchr(p,'\n');
				if(s==NULL)  
					s=strchr(p,'\r');
				if(s==NULL)  break;
				if( p[0]=='&') {
#ifdef DEBUG
			fprintf(stderr,"got & reply\n");
#endif
					got_cmd_reply(p,s-p+1);
				} else if( p[0]=='$') {
#ifdef DEBUG
			fprintf(stderr,"got $ cmd\n");
#endif
					got_new_cmd(p,s-p+1);
				} else {
					Write(r_fd,p,s-p+1);  
					relayaprs(p,s-p+1);
				}
				p = s+1;
			}
			if((p-buffer)<n) {
				lastread=n-(p-buffer);
				memcpy(buffer,p,lastread);
			}else lastread=0;
		} 
		// try send command to client
		if(mycall[0]==0) continue; // send command after login 
        	snprintf(sqlbuf,MAXLEN,"select id,concat('$',`call`,',',sn,',',pass,',',cmd) from ykcmd where `call`=\"%s\" and sendtm=\"0000-00-00 00:00:00\" order by cmdtm limit 1",mycall);
        	if(mysql_query(mysql,sqlbuf)!=0) {
#ifdef DEBUG
        fprintf(stderr,"sql %s error\n",sqlbuf);
#endif
                	continue;
        	}
        	result = mysql_store_result(mysql);
        	if (result)  // there are rows
        	{
			char cmdbuf[MAXLEN];
                	row = mysql_fetch_row(result);
                	if(row) {
				snprintf(cmdbuf,MAXLEN,"%s\r\n",row[1]);	
#ifdef DEBUG
        fprintf(stderr,"cmd %s \n",cmdbuf);
#endif
				Write(c_fd, cmdbuf, strlen(cmdbuf));
                        	snprintf(sqlbuf,MAXLEN,"update ykcmd set sendtm=now() where id=%s",row[0]);
                        	mysql_free_result(result);
#ifdef DEBUG
        fprintf(stderr,"sql %s \n",sqlbuf);
#endif
                        	if(mysql_query(mysql,sqlbuf)!=0) {
#ifdef DEBUG
        fprintf(stderr,"sql %s error\n",sqlbuf);
#endif
                                	return;
                        	}

                	} else
                        	mysql_free_result(result);
        	}
	}
}

void usage()
{
	printf("\naprstcp v1.0 - aprs relay by james@ustc.edu.cn\n");
	printf("\naprstcp x.x.x.x 14580 china.aprs2.net 14580\n\n");
	exit(0);
}

int main(int argc, char *argv[])
{
	int listen_fd;
	int llen;

	signal(SIGCHLD,SIG_IGN);
	if(argc!=5) {
		laddr="0.0.0.0";
		lport="14590";
		raddr="china.aprs2.net";
		rport="14580";
	}else {
		laddr=argv[1]; lport=argv[2];
		raddr=argv[3]; rport=argv[4];
	}
	printf("aprsrelay %s:%s -> %s:%s\n", laddr,lport,raddr,rport);

#ifndef DEBUG
	daemon_init("aprsrelay",LOG_DAEMON);
#endif

	listen_fd = Tcp_listen(laddr,lport,(socklen_t *)&llen);

	while (1) {
		struct sockaddr sa; int slen;
		slen = sizeof(sa);
		c_fd = Accept(listen_fd, &sa, (socklen_t *)&slen);
#ifdef DEBUG
		mysql=connectdb();
		Process(c_fd);
#else
		if( Fork()==0 ) {
			Close(listen_fd);
			mysql=connectdb();
			Process(c_fd);
		}
#endif
		Close(c_fd);
	}
}
