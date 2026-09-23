import { useForm } from '@inertiajs/react'

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
        <div>
            <h1>Регистрация</h1>

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="name">Имя</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />

                    {errors.name && <div>{errors.name}</div>}
                </div>

                <div>
                    <label htmlFor="number">Номер телефона</label>

                    <input
                        type="tel"
                        id="number"
                        name="number"
                        value={data.number}
                        onChange={(e) => setData('number', e.target.value)}
                    />

                    {errors.number && <div>{errors.number}</div>}
                </div>

                <div>
                    <label htmlFor="email">Почта</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    {errors.email && <div>{errors.email}</div>}
                </div>

                <div>
                    <label htmlFor="password">Пароль</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    {errors.password && <div>{errors.password}</div>}
                </div>

                <button type="submit" disabled={processing}>
                    {processing ? 'Регистрация...' : 'Зарегистрироваться'}
                </button>
            </form>
        </div>
    )
}