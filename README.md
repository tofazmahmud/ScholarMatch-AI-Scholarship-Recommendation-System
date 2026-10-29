
# ScholarMatch
### AI-Assisted Scholarship Recommendation System

## Project Overview
ScholarMatch is a web-based scholarship discovery and
recommendation system designed to help students find
scholarships based on academic qualifications and preferences.

The application combines rule-based eligibility matching,
AI-assisted CV extraction, and Retrieval-Augmented
Generation (RAG) for explainable recommendations.

## Key Features
- Scholarship Search and Filtering
- Student Academic Profile Management
- Gemini AI-Based CV Extraction
- Rule-Based Manual CV Extraction
- Rule-Based Scholarship Eligibility Matching
- Personalized Scholarship Recommendations
- RAG-Based "Why This Fits" Explanations
- Scholarship Bookmarking
- Application Tracking
- Admin Dashboard
- Scholarship and Eligibility Rules Management
- CSV Scholarship Import
- RAG Evidence Document Management
- Source Verification
- Admin Roles and Audit Logs

## Technologies Used
- PHP
- MySQL / MariaDB
- HTML
- CSS
- JavaScript
- Google Gemini API
- XAMPP
- Composer

## Project Structure
- admin/ - Administrative management
- includes/ - Backend services and business logic
- assets/ - Frontend assets
- config/ - Application configuration
- database/ - Database scripts
- tests/ - Testing scripts
- scripts/ - Utilities and database checks

## Installation and Setup
1. Install XAMPP.
2. Download or clone the repository.
3. Place the project inside the XAMPP htdocs directory.
4. Start Apache and MySQL.
5. Create a database named scholarmatch.
6. Import the provided database schema and migrations.
7. Configure local database credentials.
8. Install Composer dependencies if required.
9. Configure the Gemini API key privately.
10. Open the application through localhost.

## Local Project URL
http://localhost/scholarmatch_stable/scholarmatch/

## Testing
Testing activities include:
- PHP syntax validation
- Core business logic testing
- Admin smoke testing
- Database preflight testing

Testing details and evidence are documented separately.

## Current Limitation
Student profiles are session-associated.
Persistent student registration and login are planned
as future improvements.

## Security
API keys, passwords, private configuration files,
and user uploads must not be committed to GitHub.

## Project Contributors
Tofaz Mahmud Jehad
ID: 0432320005101049

Abdullah Toufiq
ID: 0432320005101041

## Institution
University of Information Technology and Sciences (UITS)
Department of Computer Science and Engineering

## Course
CSE0613316 - Software Project Design and Development (Lab)

## Submitted To
Al-Imtiaz
Associate Professor
Department of Computer Science and Engineering
