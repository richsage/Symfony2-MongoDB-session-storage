Symfony2 MongoDB session storage
================================

Important: This has been converted from a class that used Mandango, therefore the
passing of Mongo to the service may not be correct. This is untested! PRs welcome :-)

These are also temporary instructions until I find the time to stick
it all into a bundle and test it accordingly ;-)

1. Add this file somewhere in your project, and add the relevant namespace line, eg:

        namespace My\Bundle\Session;


2. Add the class as a service in `config.yml` or wherever your service configuration is:

        services:
            // ...
            session.storage.mongo:
                class: My\Bundle\Session\MongoDBSessionStorage
                arguments:
                    con: "%however.you.get.mongo%"
                    options: "%mongo.session.options%"


3. Add session options into your parameters (maybe in `config.yml` or `parameters.yml`):

        parameters:
            // ...
            mongo.session.options:
                db_name: myMongoDBName
                collection: session


4. Configure the session to use your new service, in `config.yml`:

        framework:
            session:
                storage_id: session.storage.mongo


