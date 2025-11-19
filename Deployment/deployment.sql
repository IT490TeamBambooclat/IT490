Create Table bundle_deployments (
	ID  INT Auto_Increment Primary Key,
	version_number INT Not Null,
	bundle_name Varchar(100) Not Null,
	file_path Varchar(1024) NOT Null,
	status ENUM('pending', 'deployed', 'passed', 'failed') Not Null default 'pending');
