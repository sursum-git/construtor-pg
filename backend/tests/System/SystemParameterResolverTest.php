<?php

namespace App\Tests\System;

use App\Entity\SystemOption;
use App\Entity\SystemOptionList;
use App\Entity\SystemParameter;
use App\Entity\SystemParameterValue;
use App\Repository\SystemOptionRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use App\Runtime\RuntimeHttpException;
use App\System\SystemParameterResolver;
use PHPUnit\Framework\TestCase;

class SystemParameterResolverTest extends TestCase
{
    public function testResolvesBooleanDefaultValue(): void
    {
        $parameter = $this->parameter('subscriber.enabled', 'boolean', false);
        $resolver = $this->resolver($parameter);

        self::assertFalse($resolver->getBoolean('subscriber.enabled'));
    }

    public function testResolvesCurrentGlobalValue(): void
    {
        $parameter = $this->parameter('subscriber.enabled', 'boolean', false);
        $value = $this->parameterValue($parameter, true);
        $resolver = $this->resolver($parameter, $value);

        self::assertTrue($resolver->getBoolean('subscriber.enabled'));
    }

    public function testPassesEstablishmentToValueLookup(): void
    {
        $parameter = $this->parameter('subscriber.enabled', 'boolean', false);
        $value = $this->parameterValue($parameter, true);
        $parameters = $this->createStub(SystemParameterRepository::class);
        $parameters->method('findEnabledByCode')->willReturn($parameter);

        $values = $this->createMock(SystemParameterValueRepository::class);
        $values->expects(self::once())
            ->method('findBestValue')
            ->with($parameter, 'loja-1')
            ->willReturn($value);

        $resolver = new SystemParameterResolver(
            $parameters,
            $values,
            $this->createStub(SystemOptionRepository::class),
        );

        self::assertTrue($resolver->getBoolean('subscriber.enabled', 'loja-1'));
    }

    public function testResolvesSingleOption(): void
    {
        $list = $this->optionList();
        $parameter = $this->parameter('login.provider', 'option', 'local', $list);
        $option = $this->option($list, 'local');
        $options = $this->createStub(SystemOptionRepository::class);
        $options->method('findActiveByCode')->willReturn($option);

        $resolver = $this->resolver($parameter, null, $options);

        self::assertSame('local', $resolver->getOption('login.provider'));
    }

    public function testBlocksSingleOptionWhenOptionIsMissingOrInactive(): void
    {
        $list = $this->optionList();
        $parameter = $this->parameter('login.provider', 'option', 'ldap', $list);
        $options = $this->createStub(SystemOptionRepository::class);
        $options->method('findActiveByCode')->willReturn(null);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Opcao do parametro nao encontrada ou inativa.');

        $this->resolver($parameter, null, $options)->getOption('login.provider');
    }

    public function testResolvesMultipleOptions(): void
    {
        $list = $this->optionList();
        $parameter = $this->parameter('program.enabled_modules', 'multi_option', ['cadastros', 'admin'], $list);
        $options = $this->createStub(SystemOptionRepository::class);
        $options->method('findActiveByCodes')->willReturn([
                $this->option($list, 'cadastros'),
                $this->option($list, 'admin'),
            ]);

        $resolver = $this->resolver($parameter, null, $options);

        self::assertSame(['cadastros', 'admin'], $resolver->getOptions('program.enabled_modules'));
    }

    public function testBlocksOptionParameterWithoutOptionList(): void
    {
        $parameter = $this->parameter('login.provider', 'option', 'local');

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Parametro exige lista de opcoes vinculada.');

        $this->resolver($parameter)->getOption('login.provider');
    }

    private function resolver(
        SystemParameter $parameter,
        ?SystemParameterValue $value = null,
        ?SystemOptionRepository $options = null,
    ): SystemParameterResolver {
        $parameters = $this->createStub(SystemParameterRepository::class);
        $parameters->method('findEnabledByCode')->willReturn($parameter);

        $values = $this->createStub(SystemParameterValueRepository::class);
        $values->method('findBestValue')->willReturn($value);

        return new SystemParameterResolver(
            $parameters,
            $values,
            $options ?? $this->createStub(SystemOptionRepository::class),
        );
    }

    private function parameter(string $code, string $dataType, mixed $defaultValue, ?SystemOptionList $list = null): SystemParameter
    {
        return (new SystemParameter())
            ->setCode($code)
            ->setName($code)
            ->setDataType($dataType)
            ->setDefaultValue($defaultValue)
            ->setOptionList($list)
            ->setEnabled(true);
    }

    private function parameterValue(SystemParameter $parameter, mixed $value): SystemParameterValue
    {
        return (new SystemParameterValue())
            ->setParameter($parameter)
            ->setValue($value);
    }

    private function optionList(): SystemOptionList
    {
        return (new SystemOptionList())
            ->setCode('login-provider')
            ->setName('Provedores')
            ->setEnabled(true);
    }

    private function option(SystemOptionList $list, string $code): SystemOption
    {
        return (new SystemOption())
            ->setOptionList($list)
            ->setCode($code)
            ->setDescription($code)
            ->setEnabled(true);
    }
}
