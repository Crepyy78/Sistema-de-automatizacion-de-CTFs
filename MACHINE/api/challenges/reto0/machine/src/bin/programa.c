#include <sys/random.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

#define PASS_LEN 32
#define MAX_MSG 2048

#define RESET   "\033[0m"
#define RED     "\033[31m"
#define GREEN   "\033[32m"
#define YELLOW  "\033[33m"
#define BLUE    "\033[34m"
#define CYAN    "\033[36m"
#define BOLD    "\033[1m"


FILE *f;

void mostrarFlag() {

	f = fopen("/flag/flag.txt", "r");
	if (!f) {
		f = fopen("flag.txt", "r");
		if (!f) {
			printf(RED "\nNo se pudo abrir /flag/flag.txt\n" RESET);
			return;
		}
	}
	printf(GREEN BOLD "\n=========== FLAG ===========\n\n" RESET);
	int c;
	while ((c = fgetc(f)) != EOF)
		putchar(c);
	printf("\n");
	fclose(f);
}


int main(void)
{
	mostrarFlag();
}
