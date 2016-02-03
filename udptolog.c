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


#define MAXLEN 16384

#define PORT 14582

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
	si_me.sin_addr.s_addr = inet_addr("127.0.0.1");
	if (bind(s, (const struct sockaddr *)&si_me, sizeof(si_me))==-1)
		diep("bind");

	while(1) {
		char buf[MAXLEN];
        	FILE *fp;
        	time_t timep;
        	struct tm *p;
        	static char fname[200];
		int len;
		len = recvfrom(s, buf, MAXLEN, 0, (struct sockaddr * )&si_other, (socklen_t *)&slen);
		if (len<10 ) continue;
		buf[len]=0;
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

		if(buf[len-1]=='\n') 
			len--; 
		if(buf[len-1]=='\r') 
			len--; 
		buf[len]=0; 
        	time(&timep);
        	p = localtime(&timep); 
        	snprintf(fname,200,"/var/log/aprs/%d%02d%02d",(1900+p->tm_year),(1+p->tm_mon), p->tm_mday);
        	fp = fopen(fname,"a+");
		if(fp) {
        		fprintf (fp,"%d%02d%02d.%02d%02d%02d %s\n",
                	(1900+p->tm_year),(1+p->tm_mon), p->tm_mday,
                	p->tm_hour, p->tm_min, p->tm_sec, 
			buf);
			fclose(fp);
		}
	}
	return 0;
}
