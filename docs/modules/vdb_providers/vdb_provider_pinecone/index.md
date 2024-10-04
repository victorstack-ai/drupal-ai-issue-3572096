# Pinecone Vector Database Provider

## Overview

This Drupal module provides integration with Pinecone, a managed vector database
service. It includes features for inserting, deleting, and managing vector data.
This module is intended to support the 'Serverless' architecture only. The VDB
provider methods around Collections therefore map to Pinecone's namespace
architecture.

If you intend to use Pinecone's Pod infrastructure, that should be provided via
a third-party module implementing a new VDB Provider.

## Requirements

- Pinecone serverless account (starter is fine) and API key.

## Installation

1. Enable the module.
2. Configure the API connection to Pine via Admin > Configuration > Vector 
   Database Providers > Pinecone.
3. Create an Index in the Pinecone UI.
4. Create a new Search API Server, select Pinecone as the backend, and select 
   that Index.
5. Set up AI Search as desired (see AI Search documentation).

## Contributing to the Pinecone PHP library dependency.

See https://github.com/probots-io/pinecone-php