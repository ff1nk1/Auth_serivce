import axios from 'axios'

export function createClient(baseURL) {
    return axios.create({
        baseURL: baseURL || '',
        headers: { Accept: 'application/json' },
        withCredentials: true,
    })
}
