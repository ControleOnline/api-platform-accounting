<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\Cep;
use ControleOnline\Entity\City;
use ControleOnline\Entity\District;
use ControleOnline\Entity\Document;
use ControleOnline\Entity\DocumentType;
use ControleOnline\Entity\Language;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\State;
use ControleOnline\Entity\Street;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceTaxPartyResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolveParty(?array $party): ?People
    {
        if (!$party || ($party['document'] ?? '') === '') {
            return null;
        }

        $documentNumber = $this->digits($party['document']);
        $documentType = strlen($documentNumber) > 11 ? 'CNPJ' : 'CPF';
        $document = $this->entityManager->getRepository(Document::class)->findOneBy([
            'document' => $documentNumber,
            'documentType' => $this->resolveDocumentType($documentType),
        ]);

        if ($document instanceof Document) {
            $people = $document->getPeople();
            if (($party['name'] ?? '') !== '' && $people->getName() === 'NAME NOT GIVEN') {
                $people->setName($party['name']);
            }

            return $people;
        }

        $people = new People();
        $people->setName($party['name'] ?: 'Name not given');
        $people->setAlias('');
        $people->setEnabled(true);
        $people->setPeopleType($documentType === 'CNPJ' ? 'J' : 'F');
        $people->setLanguage($this->resolveDefaultLanguage());

        if (($party['stateRegistration'] ?? '') !== '') {
            $people->addOtherInformations('stateRegistration', $party['stateRegistration']);
        }

        $this->entityManager->persist($people);
        $this->entityManager->flush();

        $document = new Document();
        $document->setDocument((int) $documentNumber);
        $document->setDocumentType($this->resolveDocumentType($documentType));
        $document->setPeople($people);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $people;
    }

    public function resolvePartyAddress(?People $people, ?array $party): ?Address
    {
        if (!$people instanceof People || !is_array($party['address'] ?? null)) {
            return null;
        }

        return $this->resolveAddress($people, $party['address']);
    }

    public function ensureLink(People $company, ?People $people, string $linkType): void
    {
        if (!$people instanceof People || $company === $people) {
            return;
        }

        if ($people->getId() === null) {
            $this->entityManager->flush();
        }

        $link = $this->entityManager->getRepository(PeopleLink::class)->findOneBy([
            'company' => $company,
            'people' => $people,
            'linkType' => $linkType,
        ]);

        if ($link instanceof PeopleLink) {
            return;
        }

        $link = new PeopleLink();
        $link->setCompany($company);
        $link->setPeople($people);
        $link->setLinkType($linkType);
        $link->setEnabled(true);
        $this->entityManager->persist($link);
    }

    private function resolveAddress(People $people, array $data): ?Address
    {
        $postalCode = $this->digits($data['postalCode'] ?? '');
        $streetName = trim((string) ($data['street'] ?? ''));
        $districtName = trim((string) ($data['district'] ?? ''));
        $cityName = trim((string) ($data['city'] ?? ''));
        $stateUf = strtoupper(trim((string) ($data['state'] ?? '')));

        if ($postalCode === '' || $streetName === '' || $districtName === '' || $cityName === '' || $stateUf === '') {
            return null;
        }

        $state = $this->entityManager->getRepository(State::class)->findOneBy(['uf' => $stateUf]);
        if (!$state instanceof State) {
            return null;
        }

        $city = $this->entityManager->getRepository(City::class)->findOneBy([
            'city' => $cityName,
            'state' => $state,
        ]);

        if (!$city instanceof City && ($data['cityCode'] ?? '') !== '') {
            $city = $this->entityManager->getRepository(City::class)->findOneBy([
                'cod_ibge' => (int) $data['cityCode'],
            ]);
        }

        if (!$city instanceof City) {
            $city = new City();
            $city->setCity($cityName);
            $city->setState($state);
            $city->setIbge(($data['cityCode'] ?? '') !== '' ? (int) $data['cityCode'] : null);
            $city->setSeo(false);
            $this->entityManager->persist($city);
            $this->entityManager->flush();
        }

        $district = $this->entityManager->getRepository(District::class)->findOneBy([
            'district' => $districtName,
            'city' => $city,
        ]);

        if (!$district instanceof District) {
            $district = new District();
            $district->setDistrict($districtName);
            $district->setCity($city);
            $this->entityManager->persist($district);
            $this->entityManager->flush();
        }

        $cep = $this->entityManager->getRepository(Cep::class)->findOneBy(['cep' => (int) $postalCode]);
        if (!$cep instanceof Cep) {
            $cep = new Cep();
            $cep->setCep((int) $postalCode);
            $this->entityManager->persist($cep);
            $this->entityManager->flush();
        }

        $street = $this->entityManager->getRepository(Street::class)->findOneBy([
            'street' => $streetName,
            'district' => $district,
        ]);

        if (!$street instanceof Street) {
            $street = new Street();
            $street->setStreet($streetName);
            $street->setDistrict($district);
            $street->setCep($cep);
            $street->setConfirmed(true);
            $this->entityManager->persist($street);
            $this->entityManager->flush();
        }

        $number = $this->normalizeAddressNumber($data['number'] ?? '');
        $complement = trim((string) ($data['complement'] ?? ''));
        $address = $this->entityManager->getRepository(Address::class)->findOneBy([
            'people' => $people,
            'street' => $street,
            'number' => $number,
            'complement' => $complement,
        ]);

        if ($address instanceof Address) {
            return $address;
        }

        $address = new Address();
        $address->setPeople($people);
        $address->setStreet($street);
        $address->setNumber($number);
        $address->setComplement($complement);
        $address->setNickname('NF-e');
        $address->setLatitude(0);
        $address->setLongitude(0);
        $address->setLocator('');
        $address->setOpeningTime(null);
        $address->setClosingTime(null);
        $address->setSearchFor('');
        $this->entityManager->persist($address);

        return $address;
    }

    private function resolveDefaultLanguage(): ?Language
    {
        return $this->entityManager->getRepository(Language::class)->findOneBy(['language' => 'pt-br']);
    }

    private function resolveDocumentType(string $documentType): DocumentType
    {
        $documentTypeEntity = $this->entityManager->getRepository(DocumentType::class)->findOneBy([
            'documentType' => $documentType,
        ]);

        if ($documentTypeEntity instanceof DocumentType) {
            return $documentTypeEntity;
        }

        $documentTypeEntity = new DocumentType();
        $documentTypeEntity->setDocumentType($documentType);
        $documentTypeEntity->setPeopleType($documentType === 'CNPJ' ? 'J' : 'F');
        $this->entityManager->persist($documentTypeEntity);
        $this->entityManager->flush();

        return $documentTypeEntity;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function normalizeAddressNumber(mixed $number): int
    {
        $digits = $this->digits($number);

        return $digits === '' ? 0 : (int) $digits;
    }
}
