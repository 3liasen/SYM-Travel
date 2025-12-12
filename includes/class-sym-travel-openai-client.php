<?php
/**
 * OpenAI adapter for parsing emails.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles OpenAI API calls and response validation.
 */
class SYM_Travel_OpenAI_Client {

	private const MODEL = 'gpt-4.1-mini';

	private SYM_Travel_Schema_Validator $validator;
	private SYM_Travel_Log_Repository $logger;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Schema_Validator $validator Schema validator.
	 * @param SYM_Travel_Log_Repository   $logger    Logger.
	 */
	public function __construct( SYM_Travel_Schema_Validator $validator, SYM_Travel_Log_Repository $logger ) {
		$this->validator = $validator;
		$this->logger    = $logger;
	}

	/**
	 * Parse an email body via OpenAI.
	 *
	 * @param string $email_body  Raw email content.
	 * @param array  $context     Additional identifiers (message_id, date, etc).
	 * @return array Validated payload.
	 */
	public function parse_email( string $email_body, array $context = array() ): array {
		$settings = get_option( 'sym_travel_settings', array() );
		$api_key  = $settings['openai_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			throw new RuntimeException( 'OpenAI API key missing in settings.' );
		}

		$request_body = $this->build_request_body( $email_body );

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log(
				'openai',
				'OpenAI request failed: ' . $response->get_error_message(),
				array(
					'severity'   => 'error',
					'message_id' => $context['message_id'] ?? null,
				)
			);

			throw new RuntimeException( 'OpenAI request failed.' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			throw new RuntimeException( 'Empty response from OpenAI.' );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			throw new RuntimeException( 'Unexpected OpenAI response shape.' );
		}

		$json_payload = $this->decode_json( $data['choices'][0]['message']['content'] );
		$validated    = $this->validator->validate( $json_payload );

		return $validated;
	}

	/**
	 * Build the chat completion request.
	 *
	 * @param string $email_body Email body.
	 * @return array
	 */
	private function build_request_body( string $email_body ): array {
		$prompt = $this->build_prompt( $email_body );

		return array(
			'model'    => self::MODEL,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => 'You are a flight itinerary parser. Respond with strict JSON that matches the provided schema.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'response_format' => array(
				'type' => 'json_object',
			),
		);
	}

	/**
	 * Construct prompt with schema instructions.
	 *
	 * @param string $email_body Email body.
	 * @return string
	 */
	private function build_prompt( string $email_body ): string {
		$schema = array(
			'pnr'       => 'string - booking code',
			'airline'   => 'string - airline name',
			'passengers'=> array( 'name' => 'string' ),
			'journeys'  => array(
				'segments' => array(
					'departure'      => 'airport code or city',
					'arrival'        => 'airport code or city',
					'departure_time' => 'ISO8601 datetime',
					'arrival_time'   => 'ISO8601 datetime',
					'flight_number'  => 'string (e.g., SY123)',
					'aircraft'       => 'string or null',
					'class'          => 'string or null',
				),
			),
		);

		$schema_text = wp_json_encode( $schema, JSON_PRETTY_PRINT );

		return sprintf(
			"Extract the itinerary from the following airline email.\nReturn JSON matching this schema:\n%s\nEmail:\n%s",
			$schema_text,
			$email_body
		);
	}

	/**
	 * Decode strict JSON content from OpenAI response.
	 *
	 * @param string $content Response content.
	 * @return array
	 */
	private function decode_json( string $content ): array {
		$json = json_decode( $content, true );
		if ( null === $json || ! is_array( $json ) ) {
			throw new RuntimeException( 'OpenAI response was not valid JSON.' );
		}

		return $json;
	}
}
