<?php

declare (strict_types=1);
namespace Syde\Vendor\Inpsyde\PayoneerForWoocommerce\Checkout\PaymentProcessor;

use Exception;
use Syde\Vendor\Inpsyde\PaymentGateway\PaymentGateway;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\Checkout\Authentication\TokenGeneratorInterface;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\Checkout\CheckoutExceptionInterface;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\Checkout\MisconfigurationDetector\MisconfigurationDetectorInterface;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\ListSession\ListSession\ListSessionPersistor;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\ListSession\ListSession\ListSessionProvider;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\ListSession\ListSession\PaymentContext;
use Syde\Vendor\Inpsyde\PayoneerForWoocommerce\Api\Gateway\CommandFactory\WcOrderBasedUpdateCommandFactoryInterface;
use Syde\Vendor\Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Syde\Vendor\Inpsyde\PayoneerSdk\Api\Command\Exception\CommandExceptionInterface;
use Syde\Vendor\Inpsyde\PayoneerSdk\Api\Command\Exception\InteractionExceptionInterface;
use Syde\Vendor\Inpsyde\PayoneerSdk\Api\Command\ResponseValidator\InteractionCodeFailureInterface;
use Syde\Vendor\Inpsyde\PayoneerSdk\Api\Command\UpdateListCommandInterface;
use Syde\Vendor\Inpsyde\PayoneerSdk\Api\Entities\ListSession\ListInterface;
use WC_Order;
/**
 * @psalm-type PaymentResultInfo = 'success'|'failure'
 * @psalm-type PaymentResult = array{
 *     result: PaymentResultInfo,
 *     redirect?: string
 * }
 */
abstract class AbstractPaymentProcessor implements PaymentProcessorInterface
{
    /**
     * @var MisconfigurationDetectorInterface
     */
    protected $misconfigurationDetector;
    /**
     * @var ListSessionProvider
     */
    protected $sessionProvider;
    /**
     * @var ListSessionPersistor
     */
    protected $sessionPersistor;
    /**
     * @var WcOrderBasedUpdateCommandFactoryInterface
     */
    protected $updateCommandFactory;
    /**
     * @var string
     */
    protected $transactionIdFieldName;
    /**
     * @var TokenGeneratorInterface
     */
    private $tokenGenerator;
    /**
     * @var string
     */
    private $tokenKey;
    private string $sessionHashKey;
    /**
     * @param MisconfigurationDetectorInterface $misconfigurationDetector
     * @param ListSessionProvider $sessionProvider
     * @param ListSessionPersistor $sessionPersistor
     * @param WcOrderBasedUpdateCommandFactoryInterface $updateCommandFactory
     * @param TokenGeneratorInterface $tokenGenerator
     * @param string $tokenKey
     * @param string $transactionIdFieldName
     * @param string $sessionHashKey
     */
    public function __construct(MisconfigurationDetectorInterface $misconfigurationDetector, ListSessionProvider $sessionProvider, ListSessionPersistor $sessionPersistor, WcOrderBasedUpdateCommandFactoryInterface $updateCommandFactory, TokenGeneratorInterface $tokenGenerator, string $tokenKey, string $transactionIdFieldName, string $sessionHashKey)
    {
        $this->misconfigurationDetector = $misconfigurationDetector;
        $this->sessionProvider = $sessionProvider;
        $this->sessionPersistor = $sessionPersistor;
        $this->updateCommandFactory = $updateCommandFactory;
        $this->tokenGenerator = $tokenGenerator;
        $this->tokenKey = $tokenKey;
        $this->transactionIdFieldName = $transactionIdFieldName;
        $this->sessionHashKey = $sessionHashKey;
    }
    /**
     * @param WC_Order $order
     *
     * @return array
     *
     * @psalm-return PaymentResult
     *
     * @throws CheckoutExceptionInterface
     * @throws \Inpsyde\PayoneerSdk\Api\ApiExceptionInterface
     * @throws \WC_Data_Exception
     */
    public function processPayment(WC_Order $order, PaymentGateway $gateway): array
    {
        /**
         * Add a unique token that will provide a little extra protection against
         * request forgery during webhook processing
         */
        $order->update_meta_data($this->tokenKey, $this->tokenGenerator->generateToken());
        $list = $this->sessionProvider->provide(new PaymentContext($order));
        $this->updateOrderWithSessionData($order, $list);
        $updateCommand = $this->updateCommandFactory->createUpdateCommand($order, $list);
        do_action('payoneer-checkout.before_update_list', ['longId' => $list->getIdentification()->getLongId(), 'list' => $list]);
        $list = $this->updateListSession($updateCommand);
        do_action('payoneer-checkout.list_session_updated', ['longId' => $list->getIdentification()->getLongId(), 'list' => $list]);
        /**
         * This is a workaround for PN-951. If transaction was started, but not finished
         * (for example, when the page with 3DS popup was reloaded), we want to have checkout
         * hash reset to trigger List update on the next try.
         *
         * This may be removed when the proper error handling will be added to the WebSDK.
         * Now WebSDK just fails to retrieve the List from the API after the page reload and
         * nothing happens.
         */
        wc()->session->set($this->sessionHashKey, null);
        /**
         * Also unlink the order from WC Session so it couldn't be reused with
         * another payment method.
         */
        wc()->session->set('order_awaiting_payment', \false);
        /**
         * We always signal success: The actual payment is supposed to be handled by the JS WebSDK
         * or by the hosted payment page.
         * in the customer's browser session. Our 'redirect' URL is only a fallback in case our JS
         * is somehow broken. For this reason, we also add the flag to force hosted mode.
         * The WebSDK is taking care of redirecting to 'thank-you' after finishing the transaction.
         * If this somehow does not happen, we still instruct WC to move to the payment page
         */
        return ['result' => 'success', 'redirect' => '', 'messages' => '<div></div>'];
    }
    /**
     * @throws \WC_Data_Exception
     * @throws CheckoutExceptionInterface
     */
    protected function updateOrderWithSessionData(WC_Order $order, ListInterface $list): void
    {
        $this->sessionPersistor->persist($list, new PaymentContext($order));
        $identification = $list->getIdentification();
        $transactionId = $identification->getTransactionId();
        $order->update_meta_data($this->transactionIdFieldName, $transactionId);
        $order->add_order_note(sprintf(
            /* translators: Transaction ID supplied by WooCommerce plugin */
            __('Initiating payment with transaction ID "%1$s"', 'payoneer-checkout'),
            $transactionId
        ));
        $order->set_transaction_id($identification->getLongId());
        $order->save();
    }
    /**
     * @param UpdateListCommandInterface $updateCommand
     *
     * @return ListInterface
     *
     * @throws CommandExceptionInterface If failed to update.
     */
    protected function updateListSession(UpdateListCommandInterface $updateCommand): ListInterface
    {
        try {
            return $updateCommand->execute();
        } catch (CommandExceptionInterface $commandException) {
            do_action('payoneer_for_woocommerce.update_list_session_failed', ['exception' => $commandException]);
            throw $commandException;
        }
    }
    /**
     * Take actions on payment processing failed and return fields expected by WC Payment API.
     *
     * @param WC_Order $order
     * @param \Throwable|\WP_Error|string|null $error
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
     *
     * @return array
     *
     * @psalm-return PaymentResult
     */
    protected function handleFailedPaymentProcessing(WC_Order $order, $error = null): array
    {
        $fallback = __('The payment was not processed. Please try again.', 'payoneer-checkout');
        switch (\true) {
            case $error instanceof \Throwable:
                $error = $this->produceErrorMessageFromException($error, $fallback);
                break;
            case $error instanceof \WP_Error:
                $error = $error->get_error_message();
                break;
            case is_string($error):
                break;
            default:
                $error = $fallback;
        }
        wc_add_notice($error, 'error');
        do_action('payoneer-checkout.payment_processing_failure', ['order' => $order, 'errorMessage' => $error]);
        WC()->session->set('refresh_totals', \true);
        return ['result' => 'failure', 'redirect' => ''];
    }
    /**
     * @param \Throwable $exception
     * @param string $fallback
     *
     * @return string
     * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel.High
     */
    protected function produceErrorMessageFromException(\Throwable $exception, string $fallback): string
    {
        if ($this->misconfigurationDetector->isCausedByMisconfiguration($exception)) {
            /* translators: Used after checking for misconfigured merchant credentials, e.g. when we encounter 401/INVALID_CONFIGURATION*/
            return __('Failed to initialize payment session. Payoneer Checkout is not configured properly.', 'payoneer-checkout');
        }
        $previous = $exception;
        do {
            if ($previous instanceof InteractionCodeFailureInterface) {
                $response = $previous->getSubject();
                $body = $response->getBody();
                $body->rewind();
                $json = json_decode((string) $body, \true);
                if (!$json || !isset($json['resultInfo'])) {
                    return $fallback;
                }
                return (string) $json['resultInfo'];
            }
        } while ($previous = $previous->getPrevious());
        return $fallback;
    }
    /**
     * Inspect the exceptions to carry out appropriate actions based on the given interaction code
     *
     * @param WC_Order $order
     * @param InteractionExceptionInterface $exception
     *
     * @return array
     * @psalm-return PaymentResult
     */
    protected function handleInteractionException(WC_Order $order, InteractionExceptionInterface $exception): array
    {
        do_action('payoneer-checkout.update_list_session_failed', ['exception' => $exception, 'order' => $order]);
        return $this->handleFailedPaymentProcessing($order, $exception);
    }
}
