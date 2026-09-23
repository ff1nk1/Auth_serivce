import { useForm } from '@inertiajs/react'
import '../../css/auth.css'

export default function Registration() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        number: '',
        email: '',
        password: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post('/registration')
    }

    return (
        <div className="auth-page">
            <div className="auth-card register-card">
                <div className="auth-header">
                    <h1>Create account</h1>
                    <p>Register a new account</p>
                </div>

                <form onSubmit={submit} className="auth-form">
                    <div className="form-group">
                        <label htmlFor="name">Name</label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            className="auth-input"
                            placeholder="Enter your name"
                            value={data.name}
                            onChange={(e) =>
                                setData('name', e.target.value)
                            }
                        />

                        {errors.name && (
                            <span className="error">
                                {errors.name}
                            </span>
                        )}
                    </div>

                    <div className="form-group">
                        <label htmlFor="number">Phone number</label>

                        <input
                            id="number"
                            name="number"
                            type="tel"
                            className="auth-input"
                            placeholder="+48 123 456 789"
                            value={data.number}
                            onChange={(e) =>
                                setData('number', e.target.value)
                            }
                        />

                        {errors.number && (
                            <span className="error">
                                {errors.number}
                            </span>
                        )}
                    </div>

                    <div className="form-group">
                        <label htmlFor="email">Email</label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            className="auth-input"
                            placeholder="you@example.com"
                            value={data.email}
                            onChange={(e) =>
                                setData('email', e.target.value)
                            }
                        />

                        {errors.email && (
                            <span className="error">
                                {errors.email}
                            </span>
                        )}
                    </div>

                    <div className="form-group">
                        <label htmlFor="password">Password</label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            className="auth-input"
                            placeholder="Enter your password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />

                        {errors.password && (
                            <span className="error">
                                {errors.password}
                            </span>
                        )}
                    </div>

                    <button
                        type="submit"
                        className="auth-button"
                        disabled={processing}
                    >
                        {processing
                            ? 'Registering...'
                            : 'Create account'}
                    </button>
                </form>
            </div>
        </div>
    )
}