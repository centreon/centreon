<?php
declare(strict_types=1);

namespace Tests\Core\Dashboard\Application\UseCase\FindSingleMetric;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Dashboard\Application\UseCase\FindSingleMetric\{
    FindSingleMetric,
    FindSingleMetricRequest,
    FindSingleMetricResponse,
    FindSingleMetricPresenterInterface
};
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Mockery;

beforeEach(function () {
    $this->contact = Mockery::mock(ContactInterface::class);
    $this->metricRepo = Mockery::mock(ReadMetricRepositoryInterface::class);
    $this->accessGroupRepo = Mockery::mock(ReadAccessGroupRepositoryInterface::class);
    $this->requestParameters = Mockery::mock(RequestParametersInterface::class);
    $this->contact->shouldReceive('getId')->andReturn(1);

    $this->presenter = new class implements FindSingleMetricPresenterInterface {
        public mixed $response = null;
        public function presentResponse(mixed $response): void
        {
            $this->response = $response;
        }
    };
});

it('returns a FindSingleMetricResponse for an admin user', function () {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);

    $metric = new Metric(
        id: 1,
        name: 'cpu'
    );
    $metric->setCurrentValue(12.34);
    $metric->setUnit('%');

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->with(10, 20, 'cpu', $this->requestParameters)
        ->andReturn($metric);

    $this->accessGroupRepo->shouldNotReceive('findByContact');

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(10, 20, 'cpu'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSingleMetricResponse::class)
        ->and($this->presenter->response->metricDto->id)->toBe(1)
        ->and($this->presenter->response->metricDto->name)->toBe('cpu')
        ->and($this->presenter->response->metricDto->currentValue)->toBe(12.34);
});

it('passes access groups to the repository for non-admin users', function () {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(false);

    $fakeGroups = ['g1', 'g2'];
    $this->accessGroupRepo
        ->shouldReceive('findByContact')
        ->once()
        ->with($this->contact)
        ->andReturn($fakeGroups);

    $metric = new Metric(
        id: 2,
        name: 'mem'
    );
    $metric->setUnit('MB');
    $metric->setCurrentValue(256.0);


    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->with(11, 22, 'mem', $this->requestParameters, $fakeGroups)
        ->andReturn($metric);

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(11, 22, 'mem'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSingleMetricResponse::class)
        ->and($this->presenter->response->metricDto->id)->toBe(2)
        ->and($this->presenter->response->metricDto->name)->toBe('mem');
});

it('maps a "not found" exception to NotFoundResponse', function () {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->andReturn(null);

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(1, 2, 'foo'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class);
});

it('maps other exceptions to ErrorResponse', function () {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->andThrow(new RepositoryException(
            "Error retrieving metric 'foo' for host 1, service 2",
            ['metricName'=>'bar','hostId'=>1,'serviceId'=>2]
        ));

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(1, 2, 'bar'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});
