import React, { useState, useEffect } from 'react';
import { api } from '../auth/api';
import '../css/admin.css'; 

const UsersList = () => {
  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  
  const [searchInput, setSearchInput] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');

  useEffect(() => {
    const fetchRoles = async () => {
      try {
        const response = await api.get('/roles');
        setRoles(response.data);
      } catch (error) {
        console.error("Ошибка при загрузке ролей:", error);
      }
    };
    fetchRoles();
  }, []);

  // Загружаем пользователей
  const fetchUsers = async (page, emailQuery = '') => {
    setIsLoading(true);
    try {
      const response = await api.get('/users', {
        params: { page, email: emailQuery }
      });
      
      const result = response.data;
      setUsers(result.data);
      setCurrentPage(result.current_page);
      setTotalPages(result.last_page);
    } catch (error) {
      console.error("Ошибка при загрузке пользователей:", error);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers(currentPage, appliedSearch);
  }, [currentPage, appliedSearch]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setAppliedSearch(searchInput);
    setCurrentPage(1);
  };

  const handleRoleChange = async (userId, newRoleId) => {
    const newRoleObj = roles.find(r => r.id === parseInt(newRoleId));
    const originalUsers = [...users];

    setUsers(users.map(user => 
      user.id === userId 
        ? { ...user, role_id: parseInt(newRoleId), role: newRoleObj } 
        : user
    ));

    try {
      await api.patch(`/users/${userId}/role`, { 
        role_id: parseInt(newRoleId) 
      });
    } catch (error) {
      console.error(error);
      setUsers(originalUsers);
      alert('Не удалось изменить роль');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-card">
        
        {/* Заголовок */}
        <div className="admin-header">
          <h2>Управление пользователями</h2>
        </div>
        
        {/* Панель поиска */}
        <form onSubmit={handleSearchSubmit} className="search-bar">
          <input 
            type="text" 
            className="search-input"
            placeholder="Поиск по email..." 
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
          />
          <button type="submit" className="btn btn-primary">Найти</button>
          
          {appliedSearch && (
            <button 
              type="button" 
              className="btn btn-secondary"
              onClick={() => {
                setSearchInput('');
                setAppliedSearch('');
                setCurrentPage(1);
              }} 
            >
              Сбросить
            </button>
          )}
        </form>

        {/* Таблица */}
        <div className="table-wrapper">
          {isLoading ? (
            <div className="table-loading">Загрузка данных...</div>
          ) : (
            <table className="admin-table">
              <thead>
                <tr>
                  <th style={{ width: '80px' }}>ID</th>
                  <th>Имя</th>
                  <th>Email</th>
                  <th style={{ width: '200px' }}>Роль</th>
                </tr>
              </thead>
              <tbody>
                {users.length > 0 ? (
                  users.map(user => (
                    <tr key={user.id}>
                      <td style={{ color: '#7b8794', fontWeight: 500 }}>#{user.id}</td>
                      <td style={{ fontWeight: 500 }}>{user.name}</td>
                      <td>{user.email}</td>
                      <td>
                        <select 
                          className="role-select"
                          value={user.role_id} 
                          onChange={(e) => handleRoleChange(user.id, e.target.value)}
                        >
                          {roles.map(role => (
                            <option key={role.id} value={role.id}>
                              {role.name}
                            </option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="4" className="table-empty">
                      Пользователи не найдены
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
        </div>

        {/* Пагинация */}
        <div className="pagination">
          <span className="pagination-info">
            Страница {currentPage} из {totalPages || 1}
          </span>
          
          <div className="pagination-controls">
            <button 
              className="btn btn-secondary"
              onClick={() => setCurrentPage(p => p - 1)} 
              disabled={currentPage === 1 || isLoading}
            >
              Назад
            </button>
            
            <button 
              className="btn btn-secondary"
              onClick={() => setCurrentPage(p => p + 1)} 
              disabled={currentPage >= totalPages || isLoading}
            >
              Вперед
            </button>
          </div>
        </div>

      </div>
    </div>
  );
};

export default UsersList;