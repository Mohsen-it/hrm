import unittest

from app import ZKTecoService


class FakeUser:
    def __init__(self, uid, user_id, name='Employee', privilege=14, password='1234', card=55, group_id='7'):
        self.uid = uid
        self.user_id = user_id
        self.name = name
        self.privilege = privilege
        self.password = password
        self.card = card
        self.group_id = group_id


class FakeConnection:
    def __init__(self, users):
        self.users = users
        self.set_user_calls = []
        self.delete_user_calls = []

    def get_users(self):
        return self.users

    def set_user(self, **kwargs):
        self.set_user_calls.append(kwargs)

    def delete_user(self, **kwargs):
        self.delete_user_calls.append(kwargs)


class UserCodeUpdateTest(unittest.TestCase):
    def setUp(self):
        self.connection = FakeConnection([FakeUser(41, 'OLD-001')])
        self.service = ZKTecoService('127.0.0.1')
        self.service.conn = self.connection

    def test_rename_uses_existing_uid_and_preserves_user_metadata(self):
        result = self.service.update_user_code(41, 'NEW-001')

        self.assertTrue(result['success'])
        self.assertTrue(result['renamed'])
        self.assertEqual('OLD-001', result['previous_user_id'])
        self.assertEqual([], self.connection.delete_user_calls)
        self.assertEqual(1, len(self.connection.set_user_calls))
        self.assertEqual({
            'uid': 41,
            'name': 'Employee',
            'privilege': 14,
            'password': '1234',
            'group_id': '7',
            'user_id': 'NEW-001',
            'card': 55,
        }, self.connection.set_user_calls[0])

    def test_rename_refuses_an_unknown_uid_instead_of_creating_a_replacement(self):
        result = self.service.update_user_code(99, 'NEW-001')

        self.assertFalse(result['success'])
        self.assertEqual([], self.connection.set_user_calls)
        self.assertEqual([], self.connection.delete_user_calls)

    def test_batch_rename_resolves_previous_code_to_the_same_uid(self):
        result = self.service.add_users_batch([{
            'previous_user_id': 'OLD-001',
            'user_id': 'NEW-001',
            'name': 'Employee',
        }])

        self.assertEqual(1, result['success_count'])
        self.assertEqual(0, result['failed_count'])
        self.assertEqual({'NEW-001': 41}, result['uid_map'])
        self.assertEqual([], self.connection.delete_user_calls)
        self.assertEqual(41, self.connection.set_user_calls[0]['uid'])
        self.assertEqual('NEW-001', self.connection.set_user_calls[0]['user_id'])


if __name__ == '__main__':
    unittest.main()
