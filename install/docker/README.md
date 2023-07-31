# Ampletracks Docker
- [Ampletracks Docker](#ampletracks-docker)
  - [Container repo](#container-repo)
  - [Required files](#required-files)
    - [File explanation](#file-explanation)
    - [Modifying the inventory.yml file](#modifying-the-inventoryyml-file)
  - [Building your container](#building-your-container)
    - [Installing Docker on your system](#installing-docker-on-your-system)
      - [Windows](#windows)
      - [macOS](#macos)
      - [Debian distros](#debian-distros)
    - [Build](#build)
      - [Build commands](#build-commands)
        - [Simple build command](#simple-build-command)
        - [Progressive output command](#progressive-output-command)
      - [Run container command](#run-container-command)
  - [Updating your container](#updating-your-container)

## Container repo

This repository is where you can find anything we write related to setting up a container.  
We do not publish or maintain a Docker container on Docker Hub. We do maintain this directory where we host all the files and instructions you need to build and run a Docker container running Ampletracks.

## Required files

All files required are within the [install](/install) directory. You can either clone the whole repository or download the repository as a zipped foleder and use the files in the directory.  
We do recommend cloning the repository. This is so you can occasionally pull updates.

The only file you need to use and modify is the [docker.inventory.yml](install/docker/container-inventory/docker.inventory.yml)

### File explanation

There are two files in this directory, this [README.md](install/docker/README.md) and the [Dockerfile](install/docker/Dockerfile) for building the Docker container, and a folder containing all the required files for the container.

The [docker.inventory.yml](install/docker/container-inventory/docker.inventory.yml) file sets important variables that will be passed to the install.yml script 

You will need to copy [docker.inventory.yml](install/docker/container-inventory/docker.inventory.yml) to a new file named **inventory.yml** and modify it.

### Modifying the inventory.yml file

The fields you need to modify are:

1. `ampletracks_domain_name`, this is important to name, even if it does not explicitly match the domain name, it should be descriptive
2. `ampletracks_first_user_email`, we suggest using your email address
3. `ampletracks_first_user_password`, the install script will generate a password if this variable is not set, we strongly suggest you set a password
4. `lets_encrypt_admin_email`, if you are planning on making the host ports accessible to the public. Simply uncomment the variable and set the email address
   NB: The variable needs to be at the same indent position as all other varibles

## Building your container

If you are familiar with Docker and have it installed on your system or server we suggest you start with the [Build](#build) section. Otherwise, please read below on how to install Docker on your system.

<details>
<summary>Docker on different OS</summary>

### Installing Docker on your system

#### Windows

#### macOS

#### Debian distros

</details>

### Build

1. Clone the repository to your system
2. Go to /install/docker (HERE!)
3. Run the build command
4. Run the container
5. Check that the container is up and running by running `sudo docker ps`

#### Build commands

##### Simple build command

`sudo docker build --no-cache -t ampletracks <'Your directory'>`

##### Progressive output command

`sudo docker build --no-cache --progress plain -t ampletracks <'Your directory'>`

#### Run container command

NB: The  `--publish` argument maps host port to container port, i.e. `--publish=hostPort:containerPort/tcp`. You can map whichever ports to whichever but the container is set up to open ports 80 and 443 so it is recommended you do not alter the container ports unless you want to alter the Apache configuration.

`sudo docker run -dit --publish=80:80/tcp --publish=443:443/tcp --name <container-name> ampletracks`

## Updating your container

The Ampletracks repository will have been cloned in your container as part of the container building and install process. You can update the container by executing an interactive bash command on a running container.

1. Run `sudo docker exec -it <container-name> bash` to 'enter' the container
2. Navigate to ...
3. ...
4. ...
