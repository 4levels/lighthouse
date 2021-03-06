<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Subscriptions\Exceptions\UnauthorizedSubscriber;

class SubscriptionDirective extends BaseDirective implements FieldResolver
{
    /** @var SubscriptionRegistry */
    protected $registry;

    /** @var SubscriptionExtension */
    protected $extension;

    /**
     * @param SubscriptionRegistry $registry
     * @param Extension            $extension
     */
    public function __construct(SubscriptionRegistry $registry, ExtensionRegistry $extensions)
    {
        $this->registry = $registry;
        $this->extension = $extensions->get(SubscriptionExtension::name());
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'subscription';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $subscription = $this->getSubscription();
        $fieldName = $value->getFieldName();

        $this->registry->register(
            $subscription,
            $value->getFieldName()
        );

        return $value->setResolver(function ($root, $args, $context, ResolveInfo $info) use ($subscription, $fieldName) {
            if ($root instanceof Subscriber) {
                return $subscription->resolve($root->root, $args, $context, $info);
            }

            $subscriber = Subscriber::initialize(
                $root,
                $args,
                $context,
                $info,
                $this->extension->currentQuery()
            );

            if (! $subscription->can($subscriber)) {
                throw new UnauthorizedSubscriber('Unauthorized subscription request');
            }

            $this->registry->subscriber(
                $subscriber,
                $subscription->encodeTopic($subscriber, $fieldName)
            );

            return null;
        });
    }

    /**
     * @return GraphQLSubscription
     */
    protected function getSubscription(): GraphQLSubscription
    {
        return app($this->directiveArgValue('class'));
    }
}
