export default function Profile({ user }) {
    return (
        <div>
            <h1>Profile</h1>

            <p>
                <strong>ID:</strong> {user.id}
            </p>

            <p>
                <strong>Name:</strong> {user.name}
            </p>

            <p>
                <strong>Email:</strong> {user.email}
            </p>

            <p>
                <strong>Role ID:</strong> {user.role_id}
            </p>
        </div>
    )
}