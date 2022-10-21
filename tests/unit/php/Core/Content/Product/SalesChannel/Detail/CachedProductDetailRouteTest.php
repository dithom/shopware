<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 * @covers \Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute
 */
class CachedProductDetailRouteTest extends TestCase
{
    /**
     * @var AbstractProductDetailRoute&MockObject
     */
    private $decorated;

    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    private EventDispatcher $eventDispatcher;

    private CachedProductDetailRoute $cachedRoute;

    private SalesChannelContext $context;

    private ProductDetailRouteResponse $response;

    public function setUp(): void
    {
        $this->decorated = $this->createMock(AbstractProductDetailRoute::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $this->context = new SalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex())),
            Uuid::randomHex(),
            null,
            $salesChannel,
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(1, 1.1, true),
            new CashRoundingConfig(1, 1.1, true)
        );
        $this->response = new ProductDetailRouteResponse(
            new SalesChannelProductEntity(),
            null
        );

        $this->cachedRoute = new CachedProductDetailRoute(
            $this->decorated,
            $this->cache,
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(AbstractCacheTracer::class),
            $this->eventDispatcher,
            []
        );
    }

    public function testLoadWithDisabledCacheWillCallDecoratedRoute(): void
    {
        $this->decorated
            ->expects(static::once())
            ->method('load')
            ->willReturn($this->response);
        $this->cache
            ->expects(static::never())
            ->method('get');
        $this->eventDispatcher->addListener(
            ProductDetailRouteCacheKeyEvent::class,
            fn (ProductDetailRouteCacheKeyEvent $event) => $event->disableCaching()
        );

        $this->cachedRoute->load('', new Request(), $this->context, new Criteria());
    }

    public function testLoadWithEnabledCacheWillReturnDataFromCache(): void
    {
        $this->decorated
            ->expects(static::never())
            ->method('load');
        $this->cache
            ->expects(static::once())
            ->method('get')
            ->willReturn(CacheValueCompressor::compress($this->response));
        $this->eventDispatcher->addListener(
            ProductDetailRouteCacheKeyEvent::class,
            fn (ProductDetailRouteCacheKeyEvent $event) => $event
        );

        $this->cachedRoute->load('', new Request(), $this->context, new Criteria());
    }
}