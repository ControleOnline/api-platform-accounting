<?php

namespace ControleOnlineTests\Controller;

use ControleOnline\Controller\CreateNFeAction;
use ControleOnline\Entity\InvoiceTax;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoiceTax;
use ControleOnline\Entity\People;
use ControleOnline\Service\Imports\InvoiceTaxImportProcessor;
use ControleOnline\Service\NFeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CreateNFeActionTest extends TestCase
{
    public function testCreatesOneInvoiceAndLinksEverySelectedOrder(): void
    {
        $provider = new People();
        $first = new Order();
        $second = new Order();
        $this->setId($first, 72892);
        $this->setId($second, 72890);
        $first->setProvider($provider);
        $second->setProvider($provider);

        $orders = $this->createMock(EntityRepository::class);
        $orders->expects(self::once())
            ->method('findBy')
            ->with(['id' => [72892, 72890]])
            ->willReturn([$first, $second]);

        $links = $this->createMock(EntityRepository::class);
        $links->expects(self::exactly(2))->method('findOneBy')->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnMap([
            [Order::class, $orders],
            [OrderInvoiceTax::class, $links],
        ]);
        $manager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::isInstanceOf(OrderInvoiceTax::class));
        $manager->expects(self::once())->method('flush');

        $nfe = $this->createMock(NFeService::class);
        $nfe->expects(self::once())
            ->method('createNfe')
            ->with(self::identicalTo([$first, $second]), '65')
            ->willReturn('<NFe>signed</NFe>');

        $invoice = new InvoiceTax();
        $import = $this->createMock(InvoiceTaxImportProcessor::class);
        $import->expects(self::once())
            ->method('importXmlContent')
            ->with($provider, 'nf-65-72892.xml', '<NFe>signed</NFe>')
            ->willReturn($invoice);

        $request = Request::create(
            '/orders/72892/nfe?model=65',
            'POST',
            ['model' => '65'],
            [],
            [],
            [],
            json_encode(['orderIds' => [72892, 72890]], JSON_THROW_ON_ERROR)
        );

        $response = (new CreateNFeAction($manager, $nfe, $import))($first, $request);
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($body['response']['success']);
        self::assertSame(2, $body['response']['count']);
        self::assertSame([72892, 72890], $body['response']['data']);
    }

    private function setId(Order $order, int $id): void
    {
        $property = new \ReflectionProperty(Order::class, 'id');
        $property->setValue($order, $id);
    }
}
