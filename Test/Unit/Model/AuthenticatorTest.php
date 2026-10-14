<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Test\Unit\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Config\Share;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\SocialLogin\Model\Authenticator;
use Zero\SocialLogin\Model\Confirmation;
use Zero\SocialLogin\Model\IdentityStore;
use Zero\SocialLogin\Model\LoginException;
use Zero\SocialLogin\Model\Profile;
use Zero\SocialLogin\Model\Provider\Pool;

/**
 * The collaborators are mocks so each test can add expectations to the ones it is about.
 */
#[AllowMockObjectsWithoutExpectations]
class AuthenticatorTest extends TestCase
{
    private IdentityStore&MockObject $identities;
    private CustomerRepositoryInterface&MockObject $customers;
    private AccountManagementInterface&MockObject $accounts;
    private CustomerInterfaceFactory&MockObject $customerFactory;
    private Share&MockObject $share;
    private Confirmation&MockObject $confirmation;
    /** @var array<int, CustomerInterface> */
    private array $byId = [];
    /** @var array<string, CustomerInterface> */
    private array $byEmail = [];

    protected function setUp(): void
    {
        $this->identities = $this->createMock(IdentityStore::class);
        $this->customers = $this->createMock(CustomerRepositoryInterface::class);
        $this->customers->method('getById')->willReturnCallback(
            fn ($id) => $this->byId[$id] ?? throw new NoSuchEntityException()
        );
        $this->customers->method('get')->willReturnCallback(
            fn ($email) => $this->byEmail[$email] ?? throw new NoSuchEntityException()
        );
        $this->accounts = $this->createMock(AccountManagementInterface::class);
        $this->customerFactory = $this->createMock(CustomerInterfaceFactory::class);
        $this->share = $this->createMock(Share::class);
        $this->confirmation = $this->createMock(Confirmation::class);
        $this->confirmation->method('isPending')->willReturnCallback(fn ($c) => (string) $c->getConfirmation() !== '');
    }

    private function authenticator(): Authenticator
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Authenticator(
            $this->identities,
            $this->customers,
            $this->accounts,
            $this->customerFactory,
            $storeManager,
            $this->share,
            $this->confirmation,
            new Pool()
        );
    }

    private function customer(int $id, string $email, int $websiteId = 1, ?string $confirmation = null): CustomerInterface
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('getWebsiteId')->willReturn($websiteId);
        $customer->method('getConfirmation')->willReturn($confirmation);
        $this->byId[$id] = $customer;
        $this->byEmail[$email] = $customer;

        return $customer;
    }

    private function profile(bool $verified = true, string $email = 'ann@example.com', string $first = 'Ann'): Profile
    {
        return new Profile('google', 'g-1', $email, $verified, $first, $first !== '' ? 'Lee' : '');
    }

    public function testConnectedAccountSignsIn(): void
    {
        $customer = $this->customer(7, 'old@example.com');
        $this->identities->method('findCustomerIds')->with('google', 'g-1')->willReturn([7]);
        $this->identities->expects($this->never())->method('add');
        $this->identities->expects($this->once())->method('touch')->with(7);

        $this->assertSame($customer, $this->authenticator()->login($this->profile()));
    }

    public function testConnectionOnAnotherWebsiteDoesNotCountWhenAccountsArePerWebsite(): void
    {
        $this->customer(7, 'ann@example.com', 2);
        unset($this->byEmail['ann@example.com']);
        $this->share->method('isWebsiteScope')->willReturn(true);
        $this->identities->method('findCustomerIds')->willReturn([7]);
        $created = $this->expectNewAccount();

        $this->assertSame($created, $this->authenticator()->login($this->profile()));
    }

    public function testVerifiedEmailConnectsTheExistingAccount(): void
    {
        $customer = $this->customer(5, 'ann@example.com');
        $this->identities->method('findCustomerIds')->willReturn([]);
        $this->identities->method('forCustomer')->willReturn([]);
        $this->identities->expects($this->once())->method('add')->with(5);
        $this->accounts->expects($this->never())->method('createAccount');

        $this->assertSame($customer, $this->authenticator()->login($this->profile()));
    }

    public function testUnverifiedEmailNeverTakesOverAnExistingAccount(): void
    {
        $this->customer(5, 'ann@example.com');
        $this->identities->method('findCustomerIds')->willReturn([]);
        $this->identities->expects($this->never())->method('add');

        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('Sign in with your password');
        $this->authenticator()->login($this->profile(false));
    }

    public function testExistingAccountWithAnotherAccountOfTheSameServiceIsNotOverwritten(): void
    {
        $this->customer(5, 'ann@example.com');
        $this->identities->method('findCustomerIds')->willReturn([]);
        $this->identities->method('forCustomer')->willReturn(['google' => ['identity_id' => 1]]);
        $this->identities->expects($this->never())->method('add');

        $this->expectException(LoginException::class);
        $this->authenticator()->login($this->profile());
    }

    public function testNoEmailNoAccount(): void
    {
        $this->identities->method('findCustomerIds')->willReturn([]);
        $this->accounts->expects($this->never())->method('createAccount');

        $this->expectException(LoginException::class);
        $this->authenticator()->login($this->profile(true, ''));
    }

    public function testNewAccountWithoutPasswordAndNamesFromTheEmailWhenMissing(): void
    {
        $this->identities->method('findCustomerIds')->willReturn([]);
        $created = $this->expectNewAccount('Ann Lee', '-');
        $this->identities->expects($this->once())->method('add')->with(9);

        $this->assertSame($created, $this->authenticator()->login($this->profile(true, 'ann.lee@example.com', '')));
    }

    public function testVerifiedEmailConfirmsAPendingAccount(): void
    {
        $this->customer(5, 'ann@example.com', 1, 'confirm-key');
        $this->identities->method('findCustomerIds')->willReturn([5]);
        $this->confirmation->expects($this->once())->method('confirm')->with(5);
        $this->confirmation->expects($this->never())->method('sendLink');

        $this->authenticator()->login($this->profile());
    }

    public function testUnverifiedEmailMustBeConfirmedFirst(): void
    {
        $this->customer(5, 'ann@example.com', 1, 'confirm-key');
        $this->identities->method('findCustomerIds')->willReturn([5]);
        $this->confirmation->expects($this->never())->method('confirm');
        $this->confirmation->expects($this->once())->method('sendLink');

        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('confirmation link');
        $this->authenticator()->login($this->profile(false));
    }

    public function testLinkRefusesAServiceAccountConnectedToSomeoneElse(): void
    {
        $this->customer(7, 'other@example.com');
        $this->identities->method('findCustomerIds')->willReturn([7]);
        $this->identities->expects($this->never())->method('add');

        $this->expectException(LoginException::class);
        $this->authenticator()->link($this->profile(), 5);
    }

    public function testLinkConnectsAndIsIdempotent(): void
    {
        $this->customer(5, 'me@example.com');
        $this->identities->method('findCustomerIds')->willReturnOnConsecutiveCalls([], [5]);
        $this->identities->method('forCustomer')->willReturn([]);
        $this->identities->expects($this->once())->method('add')->with(5);

        $this->authenticator()->link($this->profile(false), 5);
        $this->authenticator()->link($this->profile(false), 5);
    }

    private function expectNewAccount(string $first = 'Ann', string $last = 'Lee'): CustomerInterface
    {
        $new = $this->createMock(CustomerInterface::class);
        foreach (['setEmail', 'setFirstname', 'setLastname', 'setWebsiteId', 'setStoreId'] as $setter) {
            $new->method($setter)->willReturnSelf();
        }
        $new->expects($this->once())->method('setFirstname')->with($first);
        $new->expects($this->once())->method('setLastname')->with($last);
        $this->customerFactory->method('create')->willReturn($new);
        $created = $this->customer(9, 'created@example.com');
        $this->accounts->expects($this->once())->method('createAccount')->with($new)->willReturn($created);

        return $created;
    }
}
