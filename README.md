# laravel-crud

### Example usage

always <b>$data</b> -  should be validated

##### Create Model

```php
use N1Creator\Crud;
...
$data = [
    'name' => 'John',
    'email' => 'john@ltd.com',
    'roles' => [1,2,3],
];
Crud::model(new User)->store($data);
...
```

##### Update Model

```php
use N1Creator\Crud;
...
$data = [
    'name' => 'John Smit',
    'email' => 'john@ltd.com',
    'roles' => [3],
];
$user = User::find($id);
Crud::model($user)->update($data);
...
```
