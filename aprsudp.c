#include <stdio.h>
#include <stdlib.h>
#include <arpa/inet.h>
#include <netinet/in.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <unistd.h>
#include <string.h>
#include <time.h>
#include <syslog.h>
#include <errno.h>
#include <signal.h>
#include <ctype.h>

//#define DEBUG 1

#define MAXLEN 16384
#define PORT 14580

int daemon_proc = 0;

void diep(char *s)
{
	if(daemon_proc)
		syslog(LOG_CRIT,"%s: %s\n",s, strerror(errno));
	else
		perror(s);
	exit(1);
}

void daemon_init(void)
{	int i;
        pid_t   pid;
        if ( (pid = fork()) != 0)
                exit(0);                        /* parent terminates */
        /* 41st child continues */
        setsid();                               /* become session leader */
        signal(SIGHUP, SIG_IGN);
        if ( (pid = fork()) != 0)
                exit(0);                        /* 1st child terminates */
        chdir("/");                             /* change working directory */
        umask(0);                               /* clear our file mode creation mask */
        for (i = 0; i < 3; i++)
                close(i);
	daemon_proc = 1;
	openlog("aprsudp",LOG_PID,LOG_DAEMON);
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

void insertU(char *buf, int *len) {
	char *p;
	if(buf[*len-1]=='\n')
		(*len)--;
	if(buf[*len-1]=='\r')
		(*len)--;
	if(buf[*len-1]=='\n')
		(*len)--;
	buf[*len]=0;

	p=buf+*len;
	*p='/';
	*(p+1)='U';
	*(p+2)='D';
	*(p+3)='P';
	*(p+4)=0;
	(*len)+=4;
}

void relayaprs(char *buf, int len)
{
	sendudp(buf,len, "127.0.0.1",14581);   // forward to china.aprs2.net
	sendudp(buf,len, "120.25.100.30",14580);   // forward to aprs.hellocq.net
	if(strstr(buf,"-13>"))
                  sendudp(buf, len , "114.55.54.60",14580);   // forward -13 to lewei50.comI
	insertU(buf,&len);
	sendudp(buf,len, "127.0.0.1",14582);   // udptolog
	sendudp(buf,len, "127.0.0.1",14583);   // udptomysql 
}

int main(void)
{
	struct sockaddr_in si_me, si_other;
	int s, slen=sizeof(si_other);
#ifndef DEBUG
	daemon_init();
#endif
	if ((s=socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP))==-1)
		diep("socket");

	memset((char *) &si_me, 0, sizeof(si_me));
	si_me.sin_family = AF_INET;
	si_me.sin_port = htons(PORT);
	si_me.sin_addr.s_addr = htonl(INADDR_ANY);
	if (bind(s, (const struct sockaddr *)&si_me, sizeof(si_me))==-1)
		diep("bind");

	while(1) {
		char buf[MAXLEN];
		int len;
		len = recvfrom(s, buf, MAXLEN-10, 0, (struct sockaddr * )&si_other, (socklen_t *)&slen);
		if (len<10 ) continue;
		buf[len]=0;
		if( (buf[len-1]=='\n') || (buf[len-1]=='\r') )
			len--;
		if( (buf[len-1]=='\n') || (buf[len-1]=='\r') )
			len--;
		buf[len]=0x0d;
		buf[len+1]=0x0a;
		buf[len+2]=0;
		len += 2;
		if (strncmp(buf,"user",4)==0) {
			char *p=strstr(buf," pass ");
			if (p) {
				p+=6;
				while (isdigit(*p)) {
					*p='*';
					p++;
				}
			}
		}
		if( (strncmp(buf,"user",4)!=0)
		  &&(strstr(buf,"AP51G2:>51G2 HELLO APRS-51G2")==0) )
			relayaprs(buf, len);
	}
	return 0;
}
