This is a mocking framework that I developed after growing frustrated with PHPUnit's one.  It's still very experimental and I'm not committing to backward compatibility, but I would still like you to try it out and tell me what you think.

Usage
=====

## Creating the mock class

At this stage you need to manually mock class.  Ideally this would be done with code generation but that hasn't been implemented yet.
 
  - Create a mock object, subclassing the class that you're mocking
 
  - In its constructor, create a new $this->mocker property
 
		function __construct() {
			$this->mocker = new SS_Mocker;
		}
 
  - Override any methods that need mocking, with a method of the following form:
 
 		function methodName($params) {
			$args = func_get_args();
 			return $this->mocker->call('methodName', $args);
		}
 
  - Pass through method() and checkExpectations() to the mocker
 
		function method($method) {
			return $this->mocker->method($method);
		}

		function checkExpectations() {
			return $this->mocker->checkExpectations();
		}

## Defining stub methods
 
This short example says defines some return values for getPrimes() and getFiboacci() and specifies that each of these calls are expected, and should throw an exception if the calls are missed.
 
	$this->myMock
		->method('getPrimes')
			->withArgs(4)
				->returns(array(2, 3, 5, 7))
				->isExpected()
		->method('getFibonacci')
			->withArgs(6)
				->returns(array(1, 1, 2, 3, 5, 8))
				->isExpected()
			->withArgs(3)
				->returns(array(1, 1, 2))
				->isExpected();
 
In order for the expectations to be triggered, checkExpectations() should be called.
 
	$this->myMock->checkExpectations();
