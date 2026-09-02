From debian

copy ./src /opt/src

RUN chmod +x /opt/src/*.sh

RUN /opt/src/prepare.sh

ENTRYPOINT ["/bin/bash"]
CMD ["/opt/src/execute.sh"]
