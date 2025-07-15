# The Buwana Authentication Protocol

The Buwana system is inspired by the challenges we've had over the years developing and operating a social action platform-- while maintaining our regenerative principles.  After using with great reluctance corporate account authentication protocols, it is clear that moving forward, we need our own account system for login access to our websites and apps so that we're not dependent on for-profit services.

By casting aside the proposition of a fast and easy solution for a limited, close-source corporate authentication, we can instead we can provide a compelling online account system that fulfills our values, principles and needs-- and provide the service for other resonant organizations, movements and companies.

Much as Google, Apple, and Facebook logins-- Buwana accounts can provide a user account with core privacy and security and access methods to access GoBrik, Ecobricks.org, Open Books, Brikcoin wallet, plastic and impact accounting, and more GEA functionality.

Our vision is that this account authentication system will be useable by other resonant regenerative apps as a credential management system.

Our vision is that Buwana accounts will empower regenerative apps through their shared data-resonance.  A Buwana account will hold core community, bioregion and earthcycle data scopes that unite regenerative users and will enable these scopes to be transfereable between apps.  A user of our GoBrik app can log their ecobricks to their community, while at the same time scheduling cyclic events for this same community on Earthcal!  The Buwana system will be able to prioritize resonance, privacy and security precisely because it is presided over by a not-for-profit, for-earth enterprise (the Global Ecobrick Alliance: https://ecobricks.org/en/about.php).

Buwana accounts are stored and managed in a database separate from our main GoBrik.com and Ecobricks.org services.  

#### Where does the term 'Buwana' come from?

The word "bhuwana" (also spelled "buwana" or "bhuana") in Indonesian and other regional languages such as Balinese and Javanese also means "world" or "universe." Like "bumi," "bhuwana" has its roots in Sanskrit. The Sanskrit word "bhūvana" (भुवन) means "world," "earth," or "universe."

The connection between "bumi" and "bhuwana" lies in their shared Sanskrit origin and their similar meanings related to the concept of the world or earth. While "bumi" directly derives from "bhūmi," meaning earth or ground, "bhuwana" comes from "bhūvana," which refers to the world or universe in a broader sense. Both terms reflect the deep influence of Sanskrit on the Indonesian language and its regional variants.

## Installing Dependencies

This project uses Composer for PHP package management. After cloning the repository, run:

```
composer install
```

This command creates the `vendor/` directory with all required libraries.

## Running Tests

PHPUnit is used for unit testing. After installing dependencies with Composer, run:

```
vendor/bin/phpunit
```

The configuration file `phpunit.xml` is provided in the repository.

## Helper Functions

`check_user_app_connection()` verifies that a logged-in user has an active
connection to the requesting application. If no connection record exists the
function now redirects using an absolute path (`/$lang/app-connect.php`) so that
the redirect works even when called from nested scripts.
