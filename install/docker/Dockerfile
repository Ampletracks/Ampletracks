FROM debian:buster

MAINTAINER ampletracks

RUN apt update --fix-missing && \
apt install -y software-properties-common && \
add-apt-repository --yes -r ppa:ansible/ansible && \
apt update && \
apt install -y ansible && \
apt install -y curl && \
apt install -y net-tools

RUN mkdir ansible
ADD ./container-inventory/ansible ansible/
COPY ./container-inventory/boot_script.sh .
COPY ./container-inventory/apache_restart /etc/cron.d/
RUN echo $(ls)
RUN echo $(ls /etc/cron.d/)
RUN cd ansible/ && \
echo $(ansible-playbook -i inventory.yml -e interactive=no install.yml | tee -a out.txt) && \
cd / && \
echo $(ls) && \
chmod +x boot_script.sh
ENTRYPOINT ["/boot_script.sh"]
