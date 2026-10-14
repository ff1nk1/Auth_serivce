package httpapi

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"analytic_service/internal/store"
)

type Server struct {
	store *store.Store
}

func NewServer(st *store.Store) *Server {
	return &Server{store: st}
}

func (s *Server) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", s.handleHealthz)
	mux.HandleFunc("GET /analytics/users", s.handleUsers)
	mux.HandleFunc("GET /analytics/orders", s.handleOrders)
	mux.HandleFunc("GET /analytics/orders/stores", s.handleOrdersByStore)
	mux.HandleFunc("GET /analytics/orders/categories", s.handleOrdersByCategory)
	mux.HandleFunc("GET /analytics/search", s.handleSearch)
	return mux
}

func (s *Server) handleHealthz(w http.ResponseWriter, r *http.Request) {
	if err := s.store.Ping(r.Context()); err != nil {
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{
			"status": "unhealthy",
			"error":  err.Error(),
		})
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (s *Server) handleUsers(w http.ResponseWriter, r *http.Request) {
	from, to, err := parseRange(r)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	m, err := s.store.UsersMetrics(r.Context(), from, to)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, m)
}

func (s *Server) handleOrders(w http.ResponseWriter, r *http.Request) {
	from, to, err := parseRange(r)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	m, err := s.store.OrdersMetrics(r.Context(), from, to)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, m)
}

func (s *Server) handleSearch(w http.ResponseWriter, r *http.Request) {
	from, to, err := parseRange(r)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	limit, err := parseLimit(r, 20)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	m, err := s.store.SearchMetrics(r.Context(), from, to, limit)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, m)
}

func (s *Server) handleOrdersByStore(w http.ResponseWriter, r *http.Request) {
	from, to, err := parseRange(r)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	storeID, err := parseOptionalUint64(r, "store_id")
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	limit, err := parseLimit(r, 20)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	m, err := s.store.OrdersByStore(r.Context(), storeID, from, to, limit)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, m)
}

func (s *Server) handleOrdersByCategory(w http.ResponseWriter, r *http.Request) {
	from, to, err := parseRange(r)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	categoryID, err := parseOptionalUint64(r, "category_id")
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	limit, err := parseLimit(r, 20)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	m, err := s.store.OrdersByCategory(r.Context(), categoryID, from, to, limit)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, m)
}

func parseLimit(r *http.Request, def int) (int, error) {
	raw := r.URL.Query().Get("limit")
	if raw == "" {
		return def, nil
	}
	n, err := strconv.Atoi(raw)
	if err != nil || n < 1 || n > 100 {
		return 0, fmt.Errorf("limit must be 1..100")
	}
	return n, nil
}

func parseOptionalUint64(r *http.Request, key string) (*uint64, error) {
	raw := r.URL.Query().Get(key)
	if raw == "" {
		return nil, nil
	}
	n, err := strconv.ParseUint(raw, 10, 64)
	if err != nil || n == 0 {
		return nil, fmt.Errorf("%s must be a positive integer", key)
	}
	return &n, nil
}

func parseRange(r *http.Request) (*time.Time, *time.Time, error) {
	q := r.URL.Query()
	var from, to *time.Time

	if raw := q.Get("from"); raw != "" {
		t, err := parseDate(raw)
		if err != nil {
			return nil, nil, err
		}
		from = &t
	}
	if raw := q.Get("to"); raw != "" {
		t, err := parseDate(raw)
		if err != nil {
			return nil, nil, err
		}
		// exclusive end-of-day if date-only
		if len(raw) == 10 {
			t = t.Add(24 * time.Hour)
		}
		to = &t
	}
	return from, to, nil
}

func parseDate(raw string) (time.Time, error) {
	if len(raw) == 10 {
		return time.ParseInLocation("2006-01-02", raw, time.UTC)
	}
	return time.Parse(time.RFC3339, raw)
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}
