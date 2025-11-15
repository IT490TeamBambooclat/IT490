Create Table bundle_deployments (
	version_number BIGINT Auto_Increment Primary Key,
	bundle_name Varchar(100) Not Null,
	file_path Varchar(1024) NOT Null,
	status ENUM('pending', 'deployed', 'passed', 'failed') Not Null default 'pending');
