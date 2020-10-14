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
        - id
        - name # lodel name, site of origin
        - title # pretty name
        - set # journals || book
        - oai_id # set
        - upd # last update
        - description
        OPTIONS.METADONNEESSITE.DROITSAUTEUR
        OPTIONS.EXTRA.OPENAIRE_ACCESS_LEVEL
        OPTIONS.METADONNEESSITE.EDITEUR
        OPTIONS.METADONNEESSITE.TITRESITE
        site_url
        OPTIONS.EXTRA.PREFIXE_DOI
        OPTIONS.METADONNEESSITE.ISSN
        OPTIONS.METADONNEESSITE.ISSN_ELECTRONIQUE
        OPTIONS.METADONNEESSITE.LANGUEPRINCIPALE
    - list of documents (should be able to list identifiers)
        - id
        - identity # id sur le site lodel
        - title
        - date
        - set
        - site # of origin (lodel name) => NO is same as set
        - class (lodel class)
        - type (lodel type)
        - type_dc => NO is same as class, type
        - type_oa => NO is same as class, type
- plugin lodel (optional)
    - update oai lists when documents are updated
    - 
- script
    - update list of sites
    - update list of documents
    - delete list of sites and documents
- records:
    - dc                                     record
        dc:title                             title
        dc:creator                           creator[]
        dc:contibutor                        contributor[]
        dc:rights                            rights, accessrights
        dc:date                              issued, embargoed
        dc:publisher                         publisher[]
        dc:identifier                        identifier_url, identifier_doi
        dc:language                          language
        dc:type                              type[]
        dc:coverage                          coverage[]
        dc:subjects                          subjects[]
        dc:description                       abstract[] or description
        dc:relation                          issn, eissn
    - qdc
        dcterms:title                        title
        dcterms:alternative                  alternative[]
        dcterms:creator                      creator[]
        dcterms:contibutor                   contributor[]
        dcterms:issued                       issued
        dcterms:accessRights                 accessrights
        dcterms:available                    embargoed
        dcterms:publisher                    publisher[]
        dcterms:identifier                   identifier_url, identifier_doi
        dcterms:isPartOf                     issn, eissn
        dcterms:hasFormat                    ?
        dcterms:language                     language
        dcterms:type                         type[]
        dcterms:rights                       rights
        dcterms:extent                       extend
        dcterms:spatial                      spatial[]
        dcterms:temporal                     temporal[]
        dcterms:subjects                     subjects[]
        dctems:abstract                      abstract[]
        dctems:description                   description
        dcterms:bibliographicalCitation      bibliographicCitation[issue]
    - mets

    
## Protocol 
- listsets
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
while inotifywait -r -e attrib,modify,create,delete,move .; do
    rsync -avz --delete --exclude-from=.rsyncignore . /target
done

## TODO
  - put sql functions in one file
  - create config
    - list size
    - baseURL
    - adminEmail
    - metadatas
    - repositoryName
    - earliestDatestamp
    - deletedRecord
    - global set name
    - global set description
  - mets
    - only for publications, numero
    - mhhhh
  - dates must be UTC
    - double check modification dates it is important for incremental harvest
    
     