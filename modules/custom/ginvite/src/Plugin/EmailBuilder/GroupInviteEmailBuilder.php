<?php

namespace Drupal\ginvite\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;

/**
 * Defines the Email Builder plugin for the ginvite module.
 *
 * This mail is sent when people who do not have an account on the website yet
 * are invited into a group. It is sent in the language the inviter was using
 * the website in.
 *
 * @see ginvite_group_content_insert()
 *
 * @EmailBuilder(
 *   id = "ginvite",
 *   sub_types = {
 *     "invite" = @Translation("Group invite")
 *   }
 * )
 */
class GroupInviteEmailBuilder extends EmailProcessorBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   */
  protected Token $token;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The group content plugin manager.
   */
  protected GroupContentEnablerManagerInterface $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('language_manager'),
      $container->get('plugin.manager.group_content_enabler'),
    );
  }

  /**
   * Constructs a GroupInviteEmailBuilder object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Token $token,
    LanguageManagerInterface $language_manager,
    GroupContentEnablerManagerInterface $plugin_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
    $this->languageManager = $language_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(EmailInterface $email): void {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $email->getParam('group');
    $language = $this->languageManager->getLanguage($email->getLangcode());
    $original_language = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($language);

    $collection = $this->pluginManager->getInstalled($group->getGroupType());
    $config = $collection->getConfiguration()['group_invitation'];

    $params = $email->getParams();
    $user = $params['existing_user'];
    unset($params['existing_user']);

    $subject = $user ? $config['existing_user_invitation_subject'] : $config['invitation_subject'];
    $body = $user ? $config['existing_user_invitation_body'] : $config['invitation_body'];

    $email->setSubject($this->token->replace($subject, $params));
    $email->setBody(Markup::create($this->token->replace($body, $params)));

    $this->languageManager->setConfigOverrideLanguage($original_language);
  }

}
