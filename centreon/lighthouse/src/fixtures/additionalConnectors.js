export default {
    "id": 1,
    "type": "vmware_v6",
    "name": "VMWare1",
    "description": "Description for VMWare1",
    "pollers": [
        {"id": 101, "name": "Poller1"},
        {"id": 102, "name": "Poller2"}
    ],
    "parameters": {
        "port": 443,
        "vcenters": [
        {
            "name": "vCenter1",
            "url": "https://vcenter1.example.com/sdk",
            "username": "user1",
            "password": "password1"
        },
        {
            "name": "vCenter2",
            "url": "192.0.0.1",
            "username": "user2",
            "password": "password2"
        }
        ]
    }
}