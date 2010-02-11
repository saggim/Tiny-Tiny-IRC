JARFILE=backend.jar
BINDIR=bin
SRCDIR=src
LIBS=lib/postgresql-8.4-701.jdbc4.jar:lib/irclib.jar:lib/json_simple-1.1.jar

#javac -classpath "lib/ lib/irclib.jar:lib/json_simple-1.1.jar" -d bin -sourcepath src src/org/fox/ttirc/Master.java

all: backend.jar

classes: src/org/fox/ttirc/Master.java
	javac -classpath ${LIBS} -d ${BINDIR} -sourcepath ${SRCDIR} ${^}

backend.jar: classes
	jar cmfv manifest.mf ${JARFILE} -C ${BINDIR}/ .
