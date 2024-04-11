# Netsuite Search Iterator
This provides a native PHP iterator that allows for quick and easy integration with NetSuite's SOAP Search interface. This interface requires looping over paged results with a lot of boilerplate so this makes that process a lot easier and less error-prone.

 
## Example:
```php
<?php

$search = new ContactSearchBasic();
$search->email = new SearchStringField();
$search->email->operator = SearchStringFieldOperator::is;
$search->email->searchValue = 'joe@example.com';
$iterator = new SearchIterator($service, $search, Contact::class, 20);
try {
  foreach ($iterator as $record) {
    // process results
  }
}
catch (\SoapFault $e) {
  // Handle exception
}
catch (\NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure $e) {
  // Handle exception
}
```

For loops are more than enough to interact with this iterator, but it can also be useful connected with a generator and the `\nikic\iter` library.

```php
<?php
fetchCustomer($date) {
  $search = new CustomerSearchBasic();
  $search->lastModifiedDate = new SearchDateField();
  $search->lastModifiedDate->operator = SearchDateFieldOperator::after;
  $search->lastModifiedDate->searchValue = date(DATE_ISO8601, $date);
  $iterator = new SearchIterator($service, $search);
  foreach ($iterator as $record) {
    yield convertCustomer($record);
  }
}
// This is a pretty weird filter not to include in your search but easy to read.
$mylist = \iter\filter(fn($customer) => $customer->isActive, fetchCustomer('2020-09-09'));
foreach ($mylist as $customer) {
  print($customer->companyName);
}
```
