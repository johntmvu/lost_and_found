# Claim Approval Workflow

## Overview
The Lost & Found system now includes a complete claim approval workflow where item owners review and approve claims before revealing contact information.

## Workflow Steps

### 1. Item Posted (Status: Available)
- User logs in and posts a lost item with:
  - Title, description, photo, location
  - Item status: `available`
- Item appears in grid for all users to see

### 2. Claim Submission (Status: Pending)
- Other users browse items and click to view details
- If they recognize an item, they click "Submit a Claim"
- They provide:
  - Description/proof (required): "Why is this yours?"
  - Optional photo URL for additional proof
- Claim is created with status: `pending`
- System links claim to item and submitting user

### 3. Owner Notification
- Item owner sees a **red badge** on their item card showing pending claim count
- Badge shows: "2 claims" if multiple people have claimed it

### 4. Owner Reviews Claims
- Owner clicks their item to open modal
- Modal shows **Claims Section** with all claims:
  - Claimant name (not email yet)
  - Their description/reasoning
  - Link to proof photo if provided
  - Submission timestamp
  - Status badge (Pending/Approved/Rejected)

### 5. Owner Decision
Owner can:
- **Approve**: 
  - Clicks "✓ Approve" button
  - Claim status → `approved`
  - Item status → `claimed`
  - Other pending claims auto-rejected
  - **Email address revealed** to owner for contact
- **Reject**:
  - Clicks "✗ Reject" button
  - Claim status → `rejected`
  - Claim card grayed out
  - Item remains `available` for other claims

### 6. Contact & Return
- Owner sees approved claimant's email
- They contact each other to arrange pickup/return
- After successful return, owner clicks "✓ Mark as Returned"
- Item status → `returned`
- Item appears grayed out with "✓ Returned" badge

## Status Flow

### Item Status
```
available → claimed → returned
```

### Claim Status
```
pending → approved (contact info shared)
pending → rejected (claim denied)
```

## Visual Indicators

### On Item Cards
- **Red badge** (top-right): Shows pending claim count (owner only)
- **Orange "Claimed" badge** (top-left): Item has approved claim
- **Green "✓ Returned" badge** (top-left): Item successfully returned
- **Grayed out card**: Item is returned (still visible but inactive)

### In Modal
- **Green banner**: Item has been returned
- **Orange banner**: Item has been claimed
- **Claim cards colored by status**:
  - Blue badge: Pending
  - Green badge + contact info: Approved
  - Gray badge + faded: Rejected

## Database Schema

### New Fields Added
- `Claim.status`: ENUM('pending', 'approved', 'rejected')
- `Claim.reviewed_at`: Timestamp when owner made decision
- `Item.status`: ENUM('available', 'claimed', 'returned')
- `Item.photo`: VARCHAR(512) for image URLs

## Security Notes
- Email addresses only revealed after approval
- Only item owners can approve/reject claims
- Only item owners can mark items as returned
- Sessions track user identity throughout process
- Prepared statements prevent SQL injection

## Future Enhancements
- Email notifications when claim is approved/rejected
- In-app messaging between owner and claimant
- Photo upload instead of URL
- Claim expiration (auto-reject after X days)
- Reputation system for frequent claimants
- Admin dashboard for moderation
