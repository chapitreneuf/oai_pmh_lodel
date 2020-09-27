# OAI-PMH server for lodel

## Docs
- http://www.openarchives.org/OAI/openarchivesprotocol.html
- http://www.openarchives.org/OAI/2.0/guidelines.htm
- http://www.openarchives.org/OAI/2.0/guidelines-repository.htm


## TODO
- upgrade script
    - add
        - id_oai: should be site short name
        - prefixe_doi
        - openaire_access_level : openAccess, embargoedAccess, restrictedAccess
- harvest
    - one url for all sites, sites are haversted using sets
    - from date
    - from set
    - must be able to know latest documents from all site: must have a table with all document and their location
    - must update documents list
        - cron
            - script to fill the database
        - trigger
        - both
    - type of documents
        - should be in a config file
        - classe "publications", type "numero" 	issue 	other
        - classe "publications", type "souspartie" 	part 	other
        - classe "textes", type "article" 	article 	article
        - classe "textes", type "chronique" 	article 	other
        - classe "textes", types "compterendu" et "notedelecture" 	review 	review
        - classe "textes", type "editorial"
        

- entry script
    - oai.php
        - connect to lodel (load lodel main config)
        - create OAI server
        - sets
        - records
        - send response
- config file
    - our database name
    - credentials are in lodel config
- database
    - list of sites
        - name
        - description
    - list of documents (should be able to list identifiers)
        - title
        - date
        - site of origin (set)
        - openaire
        - class
        - type
- plugin lodel (optional)
    - update oai lists when documents are updated
    - 
- script
    - update list of sites
    - update list of documents
- records:
    - dc
        dc:title
        dc:creator
        dc:contibutor
        dc:rights
        dc:date
        dc:publisher
        dc:identifier
        dc:language
        dc:type
        dc:coverage
        dc:subjects
        dc:description
        dc:relation
    - qdc
        dcterms:title
        dcterms:alternative
        dcterms:creator
        dcterms:contibutor
        dcterms:issued
        dcterms:accessRights
        dcterms:available
        dcterms:publisher
        dcterms:identifier
        dcterms:isPartOf
        dcterms:hasFormat
        dcterms:language
        dcterms:type
        dcterms:rights
        dcterms:extent
        dcterms:spatial
        dcterms:temporal
        dcterms:subjects
        dctems:abstract
        dctems:description
        dcterms:bibliographicalCitation
    - mets

    
## Protocol 
- listset
    - must provide XML as text
    - utiliser la table site
    - http://www.openarchives.org/OAI/openarchivesprotocol.html#ListSets
- ListIdentifiers
    - http://www.openarchives.org/OAI/openarchivesprotocol.html#ListIdentifiers
    - utiliser la table records
        - identifier
        - datestamp
        - setSpec
- record:
    $example_record = array(
        'identifier' => 'a.b.c',
        'datestamp' => date('Y-m-d-H:s'),
        'set' => 'class:activity',
        'metadata' => array(
            'container_name' => 'oai_dc:dc',
            'container_attributes' => array(
                'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
                'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
                'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                'xsi:schemaLocation' =>
                'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
            ),
            'fields' => array(
                'dc:title' => 'Testing records',
                'dc:author' => 'Neis'
            )
       ));
    - misses attribute construction to nodes

## Librairies
- https://github.com/fccn/oai-pmh-core MIT jan 2018
    - only uses sql connection
    - cannot construct other data provider
- https://github.com/danielneis/oai_pmh GPL v3  may 2013
    - basic but implementation seems good
    - misses attribute construction to nodes
- https://github.com/opencultureconsulting/oai_pmh GPL v3 may 2020
    - from XML files
    - not a so bad idea 

## Rsync auto
while inotifywait -r -e modify,create,delete,move .; do
    rsync -avz --delete --exclude-from=.rsyncignore . /target
done
