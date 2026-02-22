Follow these simple steps to run the project:

1️⃣ Download the Project

    git clone https://github.com/your-username/repository-name.git

2️⃣ Move Project to XAMPP
  
    Copy the project folder and Paste it inside:
    C:\xampp\htdocs\    
    Example:
    C:\xampp\htdocs\musicians-project
    
3️⃣ Start XAMPP
    
    Open XAMPP Control Panel  
    Start: 
    ✅ Apache
    ✅ MySQL

4️⃣ Create the Database
    
    Open your browser and go to:
    http://localhost/phpmyadmin
    Click New
    Create a database (example name):
    musicians_db
    Click on the new database
    Click Import
    Select the file:
    musicians_db.sql
    It is however recommend to execute the codes sequentially.
    (It is inside the project folder).
    Click Go

5️⃣ Configure Database Connection
    
    Open the .env file (or config file inside config folder).
    Make sure database details match your XAMPP setup:
    DB_HOST=localhost
    DB_NAME=musicians_db
    DB_USER=root
    DB_PASS=  
    ⚠️ Default XAMPP MySQL password is empty.

6️⃣ Install Dependencies

Open Command Prompt inside the project folder and run:
composer install

7️⃣ Run the Project

Open your browser and go to:
http://localhost/musicians-project/public
(Replace musicians-project with your folder name.)
