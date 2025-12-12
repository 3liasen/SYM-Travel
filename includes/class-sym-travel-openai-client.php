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

		$content      = $data['choices'][0]['message']['content'];
		$json_payload = $this->decode_json( $content, $context );

		try {
			$validated = $this->validator->validate( $json_payload );
		} catch ( Exception $validation_exception ) {
			$this->logger->log(
				'openai',
				sprintf(
					'Validation failed: %s | Payload: %s',
					$validation_exception->getMessage(),
					$this->truncate_payload( $content )
				),
				array(
					'severity'   => 'error',
					'message_id' => $context['message_id'] ?? null,
				)
			);

			throw $validation_exception;
		}

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
		$sample = array(
			'pnr'       => 'ABC123',
			'airline'   => 'KLM',
			'passengers'=> array(
				array( 'name' => 'Jane Doe' ),
			),
			'journeys'  => array(
				array(
					'segments' => array(
						array(
							'departure'      => 'Billund Airport (BLL)',
							'arrival'        => 'Amsterdam Schiphol (AMS)',
							'departure_time' => '2025-12-27T06:00:00',
							'arrival_time'   => '2025-12-27T07:15:00',
							'flight_number'  => 'KL1290',
							'aircraft'       => 'Boeing 737',
							'class'          => 'Economy',
						),
					),
				),
			),
		);

		$sample_json = wp_json_encode( $sample, JSON_PRETTY_PRINT );

		return sprintf(
			"Extract the itinerary from the following airline email.\n"
			. "Rules:\n"
			. "- Always return valid JSON only.\n"
			. "- `passengers` must be a non-empty array. Each passenger must include a `name` exactly as written in the email (e.g., the 'Passenger name' line). No empty objects.\n"
			. "- All datetime values must be ISO8601 (e.g., 2025-12-27T06:00:00).\n"
			. "- Include baggage or status details only if explicitly provided.\n"
			. "Use this JSON as your structural guide (values are illustrative):\n%s\nEmail:\n%s",
			$sample_json,
			$email_body
		);
	}

	/**
	 * Decode strict JSON content from OpenAI response.
	 *
	 * @param string $content Response content.
	 * @return array
	 */
	private function decode_json( string $content, array $context ): array {
		$json = json_decode( $content, true );
		if ( null === $json || ! is_array( $json ) ) {
			$this->logger->log(
				'openai',
				'OpenAI returned invalid JSON.',
				array(
					'severity'   => 'error',
					'message_id' => $context['message_id'] ?? null,
				)
			);
			throw new RuntimeException( 'OpenAI response was not valid JSON.' );
		}

		return $json;
	}

	/**
	 * Truncate logged payloads to avoid oversized entries.
	 *
	 * @param string $payload Original payload.
	 * @return string
	 */
	private function truncate_payload( string $payload ): string {
		$payload = str_replace( array( "\r", "\n" ), ' ', $payload );
		return mb_substr( $payload, 0, 500 );
	}
}
