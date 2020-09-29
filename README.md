# Find Treasure Explorer(Cypher Game)

## ENVIRONMENT VARIABLES (Must Be Set)
</br>phone, API, token
</br>Test API : https://findtreasure.app/api/v1/games/test-v2/
## INSTALLATION
- php artisan migrate 
- php -S localhost:8000 to start an instance of the exoplorer (Multiple Instances Can be started to start traversing from multiple points in the Graph - 8001, 8002...)

## RUNNING THE APPLICATION
- URL : http://localhost:8000/api/
- Routes : </br>
/find/{path} - FILO Traversal</br>
/find-front/{path} - FIFO Traversal</br>
/clear - Clear Cache and DB</br>
