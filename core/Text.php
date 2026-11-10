<?php
/**
 * Texts of the core (order comments, messages) in English or Russian. The dictionaries are
 * generated from i18n/ together with the OpenCart language files, so both stay one source.
 */
class CoinsPay_Text {

	/** @var array */
	private $strings;

	/** @var array */
	private $fallback;

	/**
	 * @param string $lang en or ru.
	 */
	public function __construct($lang) {
		$this->fallback = self::load('en');
		$this->strings = $lang === 'en' ? $this->fallback : self::load($lang);
	}

	/**
	 * Text by key with sprintf arguments: t('note_paid', $number, $payments).
	 *
	 * @param string $key
	 * @return string
	 */
	public function t($key) {
		$args = func_get_args();
		array_shift($args);
		if (isset($this->strings[$key])) {
			$text = $this->strings[$key];
		} elseif (isset($this->fallback[$key])) {
			$text = $this->fallback[$key];
		} else {
			$text = $key;
		}
		return $args ? vsprintf($text, $args) : $text;
	}

	private static function load($lang) {
		$file = dirname(__FILE__) . '/lang/' . preg_replace('/[^a-z]/', '', (string)$lang) . '.php';
		if (!is_file($file)) {
			return array();
		}
		$strings = include $file;
		return is_array($strings) ? $strings : array();
	}
}
