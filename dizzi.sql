-- Create dizzi before using it

use dizzi;

-- tabela de usuários
create table users (
    user_id varchar(50) not null,
    user_name varchar(50),
    password_hash varchar(255) not null,
    profile_image varchar(255),
    primary key (user_id)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_general_ci;

-- tabela de votações
create table polls (
    id int auto_increment primary key,
    user_id varchar(50) not null,
    title varchar(255) not null,
    description text,
    start_time timestamp default current_timestamp,
    end_time timestamp null,
    is_finished bool default 0,
    number_votes int default 0, 
    foreign key (user_id) references users(user_id)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_general_ci;

-- tabela de códigos da votação
create table poll_codes (
	id int auto_increment primary key,
	poll_id int not null,
	code varchar(100) not null,
	is_expired bool default 0,
	foreign key (poll_id) references polls(id)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_general_ci;

-- tabela de opções de votos
create table poll_options (
	id int auto_increment primary key,
	poll_id int not null,
	option_name varchar(255) not null,
	image_url varchar(255),
	foreign key (poll_id) references polls(id)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_general_ci;

-- tabela do ledger (blocos encadeados de votos)
create table ledger (
    id int auto_increment primary key,          
    user_id varchar(50),
    poll_id int not null,                   
    option_id int,					   			
    timestamp timestamp default current_timestamp,
    previous_hash char(64) not null,
    hash char(64) not null,
    foreign key (user_id) references users(user_id),
    foreign key (poll_id) references polls(id),
    foreign key (option_id) references poll_options(id)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_general_ci;
