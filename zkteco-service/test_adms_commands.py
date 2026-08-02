import unittest

from adms_server import build_get_request_response, parse_command_result


class AdmsCommandProtocolTest(unittest.TestCase):
    def test_face_template_uses_command_id_envelope(self):
        response = build_get_request_response(
            [
                {
                    "id": 42,
                    "command_type": "face_template",
                    "command_body": "DATA UPDATE BIODATA Pin=100 Type=2 Tmp=abc",
                }
            ]
        )

        self.assertEqual(
            "C:42:DATA UPDATE BIODATA Pin=100 Type=2 Tmp=abc\r\n",
            response,
        )

    def test_successful_device_acknowledgement(self):
        result = parse_command_result("ID=42&Return=0&CMD=DATA%20UPDATE")

        self.assertEqual(42, result["command_id"])
        self.assertEqual("completed", result["status"])
        self.assertIsNone(result["error_message"])

    def test_failed_device_acknowledgement(self):
        result = parse_command_result("ID=43&Return=-1&CMD=DATA%20UPDATE")

        self.assertEqual(43, result["command_id"])
        self.assertEqual("failed", result["status"])
        self.assertEqual("Device returned -1", result["error_message"])

    def test_invalid_acknowledgement_is_rejected(self):
        self.assertIsNone(parse_command_result("Return=0"))


if __name__ == "__main__":
    unittest.main()
