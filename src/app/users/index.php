<?php

use Lib\Prisma\Classes\Prisma;

$prisma = new Prisma();

$searchTerm = $_GET['searchTerm'] ?? '';
$users = [];
$userInfo = "";
$showEditModal = $_GET['showEditModal'] ?? false;
$showNewModal = $_GET['showNewModal'] ?? false;
$showDeleteModal = $_GET['showDeleteModal'] ?? false;
$userId = $_GET['id'] ?? '';

$roles = $prisma->userRole->findMany([], 'object');

if (!empty($searchTerm)) {
    $users = $prisma->user->findMany(
        [
            "where" => [
                "name" => [
                    "contains" => $searchTerm
                ]
            ],
            "include" => [
                "userRole" => true
            ]
        ],
        'object'
    );
} else {
    $users = $prisma->user->findMany([
        'include' => [
            'userRole' => true
        ]
    ], 'object');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateId = $_POST['updateId'] ?? '';
    $deleteId = $_POST['deleteId'] ?? '';
    $create = $_POST['create'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $roleId = $_POST['roleId'] ?? '';

    if (!empty($updateId)) {
        $prisma->user->update([
            "where" => [
                "id" => $updateId
            ],
            "data" => [
                "name" => $name,
                "email" => $email,
                "userRole" => [
                    "connect" => [
                        "id" => $roleId
                    ]
                ]
            ]
        ]);
    } elseif (!empty($create)) {
        $prisma->user->create([
            "data" => [
                "name" => $name,
                "email" => $email,
                "userRole" => [
                    "connect" => [
                        "id" => $roleId
                    ]
                ]
            ]
        ]);
    }

    if (!empty($deleteId)) {
        $prisma->user->delete(["where" => ["id" => $deleteId]]);
    }

    header("Location: users");
}

if (!empty($userId)) {
    $userInfo = $prisma->user->findUnique([
        "where" => [
            "id" => $userId
        ],
        "include" => [
            "userRole" => true
        ]
    ], "object");
}
?>


<div class="h-screen grid place-items-center">
    <div class="container mx-auto px-4 md:px-6">
        <div class="flex flex-col gap-4">
            <h1 class="text-3xl font-bold text text-center uppercase">Users</h1>
            <div class="flex items-center justify-between static mt-10">
                <a href="users?showNewModal=true" class="btn bg-primary text-white" id="addNew"><i class="fa-solid fa-plus"></i></a>
                <form method="get" class="flex gap-2">
                    <input type="search" placeholder="Search" class="p-2 border rounded-lg" name="searchTerm" value="<?= $searchTerm ?>" />
                    <button type="submit" class="btn btn-primary text-white"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>

            <div class="overflow-x-auto h-96 border rounded-sm">
                <table class="table table-xs table-pin-rows table-pin-cols">
                    <thead>
                        <tr>
                            <th></th>
                            <td>ID</td>
                            <td>Name</td>
                            <td>Email</td>
                            <td>Role</td>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 0;
                        foreach ($users as $user) : ?>
                            <tr>
                                <th><?= ++$count ?></th>
                                <td><?= $user->id ?></td>
                                <td><?= $user->name ?></td>
                                <td><?= $user->email ?></td>
                                <td><?= $user->userRole->name ?></td>
                                <td>
                                    <div class="join">
                                        <a class="btn btn-sm btn-primary join-item edit-button" href="users/id?id=<?= $user->id ?>"><i class="fa-regular fa-eye"></i></a>
                                        <a class="btn btn-sm btn-accent join-item edit-button" href="users?showEditModal=true&id=<?= $user->id ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                                        <a href="users?showDeleteModal=true&id=<?= $user->id ?>" class="btn btn-sm btn-error join-item delete-button"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Modal -->
<dialog class="modal" <?= $showNewModal === "true" ? "open" : "" ?>>
    <div class="modal-box backdrop:bg-slate-900 shadow-xl">
        <form method="dialog">
            <a href="users" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</a>
        </form>
        <h3 class="font-bold text-lg">User</h3>
        <form method="post" class="flex flex-col gap-3 w-full">
            <input id="updateId" name="create" readonly value="create" />
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Name</span>
                </div>
                <input type="text" id="name" placeholder="Name" class="input input-bordered w-full" name="name" />
            </label>
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Email</span>
                </div>
                <input type="email" id="email" placeholder="Email" class="input input-bordered w-full" name="email" />
            </label>
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Role</span>
                </div>
                <select class="select select-bordered" name="roleId" id="roleId">
                    <option disabled selected>Role</option>
                    <?php foreach ($roles as $role) : ?>
                        <option value="<?= $role->id ?>"><?= $role->name ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</dialog>


<!-- Edit Modal -->
<dialog class="modal" <?= $showEditModal === "true" ? "open" : "" ?>>
    <div class="modal-box backdrop:bg-slate-900 shadow-xl">
        <form method="dialog">
            <a href="users" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</a>
        </form>
        <h3 class="font-bold text-lg">User</h3>
        <form method="post" class="flex flex-col gap-3 w-full">
            <input id="updateId" name="<?= $userInfo->id ? "updateId" : "create" ?>" readonly value="<?= $userInfo->id ?? "" ?>" />
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Name</span>
                </div>
                <input type="text" id="name" placeholder="Name" class="input input-bordered w-full" name="name" value="<?= $userInfo->name ?? "" ?>" />
            </label>
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Email</span>
                </div>
                <input type="email" id="email" placeholder="Email" class="input input-bordered w-full" name="email" value="<?= $userInfo->email ?? "" ?>" />
            </label>
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Role</span>
                </div>
                <select class="select select-bordered" name="roleId" id="roleId">
                    <option disabled selected>Role</option>
                    <?php foreach ($roles as $role) : ?>
                        <option <?= $userInfo->userRole->id === $role->id ? "selected" : "false" ?> value="<?= $role->id ?>"><?= $role->name ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</dialog>

<!-- Delete Modal -->
<dialog id="deleteModal" class="modal" <?= $showDeleteModal === "true" ? "open" : "" ?>>
    <div class="modal-box">
        <form method="dialog">
            <a href="users" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</a>
        </form>
        <h3 class="font-bold text-lg">Delete</h3>
        <p class="py-4">Are you sure want to delete the user: <strong><span id="name"><?= $userInfo->name ?? "" ?></span></strong></p>
        <form method="post" class="flex flex-col gap-3">
            <input id="deleteId" name="deleteId" readonly value="<?= $userInfo->id ?? "" ?>" />
            <button type=" submit" class="btn btn-error">Delete</button>
        </form>
    </div>
</dialog>