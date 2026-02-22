Micey-Form
    A lightweight internal transaction system built to prevent direct access to the primary financial spreadsheet. Designed to separate transaction input workflow from core financial records while maintaining monitoring control.

Overview
Micey-Form is an internal tool created for controlled transaction input and monitoring.
Instead of giving direct access to the main financial spreadsheet, this system acts as a buffer layer:
    Users submit transactions
    Transactions appear in a monitored list
    The administrator manually confirms and transfers data to the main spreadsheet
    This approach reduces risk and keeps financial records safer.

Problem It Solves
    Prevents unauthorized access to the primary spreadsheet
    Separates transaction input from financial control
    Provides monitoring before confirmation
    Reduces accidental modification of core records

Current Features
    Transaction input form
    Transaction listing page
    Summary cards (Income, Expense, Net)
    Transaction type filtering
    Clean minimal UI

Tech Stack
    PHP (Vanilla)
    MySQL
    HTML / CSS

Installation
    Clone the repository
    Rename db.example.php to db.php
    Update the configuration settings to match your database
